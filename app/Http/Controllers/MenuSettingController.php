<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Services\MenuManager;
use Illuminate\Http\Request;

/**
 * Kelola aktivasi/deaktivasi menu sidebar per role (Admin Master & Operator
 * Daerah). Dikelola oleh Tim IT (dan Master sebagai super-admin).
 */
class MenuSettingController extends Controller
{
    public function __construct(private MenuManager $menus) {}

    public function index()
    {
        $catalog = $this->menus->catalog();
        $states  = [];
        foreach (MenuManager::MANAGED_ROLES as $role) {
            $states[$role] = $this->menus->statesFor($role);
        }

        return view('admin.menu-settings', [
            'catalog' => $catalog,
            'states'  => $states,
            'manager' => $this->menus,
        ]);
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'role'      => 'required|in:master,daerah',
            'enabled'   => 'nullable|array',
            'enabled.*' => 'string',
        ]);

        $role  = $data['role'];
        $valid = array_keys($this->menus->catalog()[$role] ?? []);
        // Hanya terima key yang benar-benar ada di katalog role tsb.
        $enabled = array_values(array_intersect($data['enabled'] ?? [], $valid));

        $this->menus->saveRole($role, $enabled);

        AuditLog::record('menu.updated', 'Ubah aktivasi menu ' . $this->menus->roleLabel($role), null, [
            'role'         => $role,
            'menu_aktif'   => $enabled,
            'menu_nonaktif' => array_values(array_diff($valid, $enabled)),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Pengaturan menu ' . $this->menus->roleLabel($role) . ' disimpan.',
        ]);
    }
}
