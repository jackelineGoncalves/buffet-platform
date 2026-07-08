import { Link } from '@inertiajs/react';

export default function AdminIndex() {
    return (
        <div className="min-h-screen bg-gray-50 p-8">
            <h1 className="mb-8 text-2xl font-bold text-gray-800">
                Panel de administración
            </h1>

            <div className="grid grid-cols-1 gap-4 sm:grid-cols-3">
                <Link
                    href="/admin/dishes"
                    className="rounded-lg border bg-white p-6 shadow-sm hover:shadow-md"
                >
                    <h2 className="text-lg font-semibold text-gray-700">
                        🍽 Platos
                    </h2>
                    <p className="mt-1 text-sm text-gray-500">
                        Crear, editar, activar y desactivar platos del menú.
                    </p>
                </Link>

                <Link
                    href="/admin/settings"
                    className="rounded-lg border bg-white p-6 shadow-sm hover:shadow-md"
                >
                    <h2 className="text-lg font-semibold text-gray-700">
                        ⚙️ Configuración
                    </h2>
                    <p className="mt-1 text-sm text-gray-500">
                        Precio de buffet, cargo por desperdicio, IVA y tiempos de sesión.
                    </p>
                </Link>

                <Link
                    href="/admin/users"
                    className="rounded-lg border bg-white p-6 shadow-sm hover:shadow-md"
                >
                    <h2 className="text-lg font-semibold text-gray-700">
                        👥 Usuarios
                    </h2>
                    <p className="mt-1 text-sm text-gray-500">
                        Ver personal, cambiar roles y registrar nuevos usuarios.
                    </p>
                </Link>
            </div>
        </div>
    );
}
