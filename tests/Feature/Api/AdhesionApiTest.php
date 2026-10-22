<?php

namespace Tests\Feature\Api;

use App\Models\AdherentDossier;
use App\Models\AdhesionLink;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdhesionApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_active_public_link_records_a_visit(): void
    {
        $link = AdhesionLink::create(['code' => 'CAMPAGNE1', 'nom' => 'Campagne', 'actif' => true]);

        $this->getJson("/api/links/{$link->code}")
            ->assertOk()
            ->assertJsonPath('actif', true);

        $this->assertDatabaseHas('link_visits', ['adhesion_link_id' => $link->id]);
    }

    public function test_inactive_public_link_is_reported_without_recording_a_visit(): void
    {
        $link = AdhesionLink::create(['code' => 'FERME2026', 'nom' => 'Lien fermé', 'actif' => false]);

        $this->getJson("/api/links/{$link->code}")
            ->assertOk()
            ->assertJsonPath('actif', false);
        $this->assertDatabaseCount('link_visits', 0);
    }

    public function test_public_submission_requires_the_previous_union_name_when_applicable(): void
    {
        $link = AdhesionLink::create(['code' => 'CAMPAGNE2', 'nom' => 'Campagne', 'actif' => true]);

        $this->postJson("/api/links/{$link->code}/dossiers", $this->validSubmission([
            'dejaSyndique' => true,
            'syndicats' => '',
        ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('syndicats');

        $this->assertDatabaseCount('adherent_dossiers', 0);
    }

    public function test_public_submission_saves_documents_privately_and_generates_a_dossier_number(): void
    {
        Storage::fake('local');
        $link = AdhesionLink::create(['code' => 'CAMPAGNE3', 'nom' => 'Campagne', 'actif' => true]);

        $response = $this->postJson("/api/links/{$link->code}/dossiers", $this->validSubmission());

        $response->assertCreated()->assertJsonStructure(['id', 'numero', 'created_at']);
        $dossier = AdherentDossier::firstOrFail();
        $this->assertStringStartsWith('DOS-'.now()->format('Y').'-', $dossier->numero);
        $this->assertSame('SYNESCI', $dossier->syndicat_precedent);
        $this->assertSame('Aminata', $dossier->prenoms);
        Storage::disk('local')->assertExists($dossier->photo_path);
        Storage::disk('local')->assertExists($dossier->signature_path);

        $this->get("/api/admin/dossiers/{$dossier->id}/documents/photo")->assertUnauthorized();
        Sanctum::actingAs($this->adminUser());
        $list = $this->getJson('/api/admin/dossiers')->assertOk();
        $photoUrl = $list->json('data.0.documents.photo');
        $this->assertStringEndsWith("/api/admin/dossiers/{$dossier->id}/documents/photo", (string) parse_url($photoUrl, PHP_URL_PATH));
        $this->get($photoUrl)->assertOk();

        $this->deleteJson("/api/admin/dossiers/{$dossier->id}")->assertOk();
        $this->assertDatabaseMissing('adherent_dossiers', ['id' => $dossier->id]);
        Storage::disk('local')->assertMissing($dossier->photo_path);
        Storage::disk('local')->assertMissing($dossier->cni_recto_path);
        Storage::disk('local')->assertMissing($dossier->cni_verso_path);
        Storage::disk('local')->assertMissing($dossier->signature_path);
    }

    public function test_bureau_api_requires_authentication_and_returns_dashboard_for_a_token_user(): void
    {
        $this->getJson('/api/admin/dashboard')->assertUnauthorized();
        $this->get('/api/admin/dashboard')
            ->assertUnauthorized()
            ->assertJsonPath('message', 'Unauthenticated.');

        Sanctum::actingAs($this->adminUser());
        $this->getJson('/api/admin/dashboard')
            ->assertOk()
            ->assertJsonPath('dossiers_total', 0)
            ->assertJsonStructure([
                'annees',
                'par_mois' => [['month', 'visits', 'submissions']],
                'par_annee' => [['annee', 'visites', 'soumissions']],
                'par_lien',
                'dernieres_adhesions',
            ]);
    }

    public function test_dossier_pagination_rejects_invalid_page_sizes(): void
    {
        Sanctum::actingAs($this->adminUser());

        $this->getJson('/api/admin/dossiers?per_page=1000')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('per_page');
    }

    public function test_deleting_a_dossier_requires_authentication(): void
    {
        $this->deleteJson('/api/admin/dossiers/1')->assertUnauthorized();
    }

    public function test_login_returns_a_sanctum_token_for_valid_credentials(): void
    {
        $user = User::factory()->create([
            'email' => 'bureau@example.ci',
            'password' => 'password',
        ]);
        $user->assignRoles(['admin']);

        $response = $this->postJson('/api/auth/login', [
            'email' => 'bureau@example.ci',
            'password' => 'password',
        ])->assertOk()->assertJsonStructure(['token', 'user' => ['id', 'name', 'email', 'roles', 'permissions']]);

        $this->withToken($response->json('token'))
            ->getJson('/api/auth/me')
            ->assertOk()
            ->assertJsonPath('email', 'bureau@example.ci');
    }

    public function test_roles_restrict_dossier_documents_deletion_export_and_dashboard(): void
    {
        Storage::fake('local');
        $link = AdhesionLink::create(['code' => 'RBAC2026', 'nom' => 'Test rôles', 'actif' => true]);
        $response = $this->postJson("/api/links/{$link->code}/dossiers", $this->validSubmission())->assertCreated();
        $dossierId = $response->json('id');

        $viewer = User::factory()->create();
        $viewer->assignRoles(['adhesion_viewer']);
        Sanctum::actingAs($viewer);

        $list = $this->getJson('/api/admin/dossiers')->assertOk();
        $this->assertNull($list->json('data.0.documents'));
        $this->getJson('/api/admin/dashboard')->assertForbidden();
        $this->getJson('/api/admin/dossiers/export')->assertForbidden();
        $this->deleteJson("/api/admin/dossiers/{$dossierId}")->assertForbidden();
        $this->patchJson("/api/admin/dossiers/{$dossierId}", ['nom' => 'Modifié'])->assertForbidden();
        $this->get("/api/admin/dossiers/{$dossierId}/documents/photo")->assertForbidden();

        $documentViewer = User::factory()->create();
        $documentViewer->assignRoles(['document_viewer']);
        Sanctum::actingAs($documentViewer);
        $documentUrl = $this->getJson('/api/admin/dossiers')->assertOk()->json('data.0.documents.photo');
        $this->get($documentUrl)->assertOk();
    }

    public function test_admin_can_manage_users_roles_and_last_admin_cannot_be_removed(): void
    {
        Sanctum::actingAs($admin = $this->adminUser());
        $this->getJson('/api/admin/users')->assertOk()->assertJsonStructure(['data', 'roles']);

        $created = $this->postJson('/api/admin/users', [
            'name' => 'Lecteur',
            'email' => 'lecteur@example.ci',
            'password' => 'motdepasse-long',
            'roles' => ['adhesion_viewer', 'adhesion_exporter'],
        ])->assertCreated()->assertJsonPath('data.permissions.0', 'adhesions.view');

        $this->patchJson('/api/admin/users/'.$created->json('data.id'), [
            'roles' => ['link_manager'],
        ])->assertOk()->assertJsonPath('data.roles.0', 'link_manager');

        $this->patchJson('/api/admin/users/'.$admin->id, [
            'roles' => ['adhesion_viewer'],
        ])->assertUnprocessable();

        $this->deleteJson('/api/admin/users/'.$admin->id)->assertUnprocessable();
    }

    public function test_specific_roles_can_modify_delete_export_and_generate_links(): void
    {
        Storage::fake('local');
        $link = AdhesionLink::create(['code' => 'ACTIONS01', 'nom' => 'Actions', 'actif' => true]);
        $dossier = $this->postJson("/api/links/{$link->code}/dossiers", $this->validSubmission())->assertCreated();

        $editor = User::factory()->create();
        $editor->assignRoles(['adhesion_editor']);
        Sanctum::actingAs($editor);
        $this->patchJson('/api/admin/dossiers/'.$dossier->json('id'), ['nom' => 'NOUVEAU NOM'])
            ->assertOk()
            ->assertJsonPath('nom', 'NOUVEAU NOM');

        $exporter = User::factory()->create();
        $exporter->assignRoles(['adhesion_exporter']);
        Sanctum::actingAs($exporter);
        $export = $this->get('/api/admin/dossiers/export')
            ->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $this->assertStringContainsString('NOUVEAU NOM', $export->streamedContent());

        $linkManager = User::factory()->create();
        $linkManager->assignRoles(['link_manager']);
        Sanctum::actingAs($linkManager);
        $this->postJson('/api/admin/links', ['nom' => 'Nouvelle campagne'])
            ->assertCreated()
            ->assertJsonPath('data.nom', 'Nouvelle campagne');

        $deleter = User::factory()->create();
        $deleter->assignRoles(['adhesion_deleter']);
        Sanctum::actingAs($deleter);
        $this->deleteJson('/api/admin/dossiers/'.$dossier->json('id'))->assertOk();
    }

    private function adminUser(): User
    {
        $user = User::factory()->create();
        $user->assignRoles(['admin']);

        return $user;
    }

    private function validSubmission(array $overrides = []): array
    {
        $image = imagecreatetruecolor(2, 2);
        ob_start();
        imagepng($image);
        $dataUrl = 'data:image/png;base64,'.base64_encode((string) ob_get_clean());
        imagedestroy($image);

        return array_replace([
            'civilite' => 'Mme',
            'nom' => 'TRAORE',
            'prenoms' => 'Aminata',
            'matricule' => '204518K',
            'emploi' => 'Instituteur(trice)',
            'fonctionSyndicale' => '',
            'employeur' => 'MENA',
            'etablissement' => 'EPP Cocody Danga',
            'dejaSyndique' => true,
            'syndicats' => 'SYNESCI',
            'telephone' => '0708123456',
            'whatsapp' => '',
            'lieu' => 'Abidjan',
            'precompte' => true,
            'consentement' => true,
            'photo' => $dataUrl,
            'cniRecto' => $dataUrl,
            'cniVerso' => $dataUrl,
            'signature' => ['data' => $dataUrl],
        ], $overrides);
    }
}
