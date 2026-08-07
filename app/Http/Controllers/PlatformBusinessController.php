<?php

namespace App\Http\Controllers;

use App\Http\Requests\Platform\StoreBusinessRequest;
use App\Http\Requests\Platform\UpdateWhatsAppCredentialsRequest;
use App\Models\Business;
use App\Models\IntegrationCredential;
use App\Models\Role;
use App\Models\User;
use App\Services\Audit\AuditLogService;
use App\Services\WhatsApp\WhatsAppCredentialManager;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Panel platform owner (Fase 8a/8b/8d) — lintas bisnis, bukan operasional satu bisnis.
 * Dibatasi middleware 'role:platform_owner' di routes/web.php, bukan lewat BusinessPolicy
 * (policy itu sengaja tetap khusus staf bisnis yang sama, lihat App\Policies\BusinessPolicy).
 */
class PlatformBusinessController extends Controller
{
    public function __construct(
        private readonly WhatsAppCredentialManager $credentials,
        private readonly AuditLogService $auditLog,
    ) {}

    public function index(): View
    {
        $businesses = Business::query()
            ->withCount(['users', 'leads'])
            ->orderBy('name')
            ->get();

        return view('platform.businesses.index', ['businesses' => $businesses]);
    }

    public function create(): View
    {
        return view('platform.businesses.create');
    }

    /**
     * Daftarkan bisnis (tenant) baru SEKALIGUS akun admin pertamanya dalam satu transaksi —
     * onboarding manual oleh platform owner (keputusan arsitektur Fase 8, bukan self-service
     * signup klien). Kredensial WhatsApp diisi belakangan lewat halaman show() setelah App
     * Meta klien siap, bukan di form ini — biar satu form tidak terlalu panjang/menakutkan.
     */
    public function store(StoreBusinessRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $business = DB::transaction(function () use ($data, $request) {
            $business = Business::create([
                'name' => $data['name'],
                'timezone' => $data['timezone'],
                'is_active' => true,
            ]);

            $adminRole = Role::query()->firstOrCreate(['slug' => Role::ADMIN], ['name' => 'Administrator']);

            $admin = User::create([
                'name' => $data['admin_name'],
                'email' => $data['admin_email'],
                'password' => Hash::make($data['admin_password']),
                'business_id' => $business->id,
                'is_active' => true,
            ]);
            $admin->roles()->attach($adminRole);

            $this->auditLog->record(
                action: 'business.created',
                subject: $business,
                after: $business->only(['name', 'timezone', 'is_active']),
                actor: $request->user(),
                actorType: 'user',
            );

            // Password TIDAK PERNAH ditulis ke audit_logs — sama seperti aturan kredensial
            // integrasi (lihat WhatsAppCredentialManager).
            $this->auditLog->record(
                action: 'user.created_as_business_admin',
                subject: $admin,
                after: ['name' => $admin->name, 'email' => $admin->email, 'business_id' => $business->id],
                actor: $request->user(),
                actorType: 'user',
            );

            return $business;
        });

        return redirect()->route('platform.businesses.show', $business)
            ->with('status', "Bisnis \"{$business->name}\" berhasil dibuat. Admin pertama: {$data['admin_email']}.");
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
