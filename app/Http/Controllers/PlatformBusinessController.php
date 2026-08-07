<?php

namespace App\Http\Controllers;

use App\Http\Requests\Platform\UpdateWhatsAppCredentialsRequest;
use App\Models\Business;
use App\Models\IntegrationCredential;
use App\Services\WhatsApp\WhatsAppCredentialManager;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

/**
 * Panel platform owner (Fase 8a/8b) — lintas bisnis, bukan operasional satu bisnis. Dibatasi
 * middleware 'role:platform_owner' di routes/web.php, bukan lewat BusinessPolicy (policy itu
 * sengaja tetap khusus staf bisnis yang sama, lihat App\Policies\BusinessPolicy).
 *
 * Fase 8b menambah kelola kredensial WhatsApp per bisnis. Form "Tambah Bisnis Baru" menyusul
 * di Fase 8d — lihat docs/STATUS.md.
 */
class PlatformBusinessController extends Controller
{
    public function __construct(private readonly WhatsAppCredentialManager $credentials) {}

    public function index(): View
    {
        $businesses = Business::query()
            ->withCount(['users', 'leads'])
            ->orderBy('name')
            ->get();

        return view('platform.businesses.index', ['businesses' => $businesses]);
    }

    public function show(Business $business): View
    {
        return view('platform.businesses.show', [
            'business' => $business,
            'credentialStatus' => $this->credentials->status($business),
            'credentialKeys' => [
                IntegrationCredential::WHATSAPP_KEY_TOKEN => 'Access Token',
                IntegrationCredential::WHATSAPP_KEY_PHONE_NUMBER_ID => 'Phone Number ID',
                IntegrationCredential::WHATSAPP_KEY_APP_SECRET => 'App Secret',
                IntegrationCredential::WHATSAPP_KEY_VERIFY_TOKEN => 'Verify Token (webhook)',
            ],
        ]);
    }

    public function updateWhatsAppCredentials(UpdateWhatsAppCredentialsRequest $request, Business $business): RedirectResponse
    {
        $this->credentials->save($business, $request->validated(), $request->user());

        return redirect()->route('platform.businesses.show', $business)
            ->with('status', 'Kredensial WhatsApp diperbarui. Field yang dikosongkan tidak diubah.');
    }
}
