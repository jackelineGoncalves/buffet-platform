import { Link, useForm } from '@inertiajs/react';

const ROLE_LABELS = { admin: 'Admin', kitchen: 'Cocina', floor: 'Sala' };

function RoleSelect({ user }) {
    const { data, setData, patch, processing } = useForm({ role: user.role });

    const handleChange = (e) => {
        const role = e.target.value;
        setData('role', role);
        patch(`/admin/users/${user.id}/role`, { data: { role } });
    };

    return (
        <select
            value={data.role}
            onChange={handleChange}
            disabled={processing}
            className="rounded border px-2 py-1 text-sm disabled:opacity-50"
        >
            <option value="admin">Admin</option>
            <option value="kitchen">Cocina</option>
            <option value="floor">Sala</option>
        </select>
    );
}

export default function AdminUsers({ users }) {
    return (
        <div className="min-h-screen bg-gray-50 p-8">
            <div className="mb-6 flex items-center justify-between">
                <div className="flex items-center gap-4">
                    <Link href="/admin" className="text-sm text-gray-500 hover:text-gray-700">
                        ← Admin
                    </Link>
                    <h1 className="text-2xl font-bold text-gray-800">Usuarios</h1>
                </div>
                <Link
                    href="/register"
                    className="rounded bg-blue-600 px-4 py-2 text-sm text-white"
                >
                    + Nuevo usuario
                </Link>
            </div>

            <div className="overflow-hidden rounded-lg border bg-white shadow-sm">
                <table className="w-full text-sm">
                    <thead className="bg-gray-50 text-xs uppercase text-gray-500">
                        <tr>
                            <th className="px-4 py-3 text-left">Nombre</th>
                            <th className="px-4 py-3 text-left">Email</th>
                            <th className="px-4 py-3 text-left">Rol</th>
                            <th className="px-4 py-3 text-left">Registrado</th>
                        </tr>
                    </thead>
                    <tbody className="divide-y">
                        {users.map((user) => (
                            <tr key={user.id}>
                                <td className="px-4 py-3 font-medium">{user.name}</td>
                                <td className="px-4 py-3 text-gray-500">{user.email}</td>
                                <td className="px-4 py-3">
                                    <RoleSelect user={user} />
                                </td>
                                <td className="px-4 py-3 text-gray-400">
                                    {new Date(user.created_at).toLocaleDateString('es-MX')}
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </div>
    );
}
