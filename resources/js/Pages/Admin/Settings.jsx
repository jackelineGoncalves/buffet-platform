import { Link, useForm, usePage } from '@inertiajs/react';

export default function AdminSettings({ setting }) {
    const { flash } = usePage().props;

    const { data, setData, put, processing, errors } = useForm({
        buffet_price: setting?.buffet_price ?? '',
        waste_fee: setting?.waste_fee ?? '',
        tax_rate: setting?.tax_rate ?? '',
        session_minutes: setting?.session_minutes ?? '',
        last_call_minutes: setting?.last_call_minutes ?? '',
    });

    const handleSubmit = (e) => {
        e.preventDefault();
        put('/admin/settings');
    };

    return (
        <div className="min-h-screen bg-gray-50 p-8">
            <div className="mb-6 flex items-center gap-4">
                <Link href="/admin" className="text-sm text-gray-500 hover:text-gray-700">
                    ← Admin
                </Link>
                <h1 className="text-2xl font-bold text-gray-800">Configuración</h1>
            </div>

            {flash?.success && (
                <div className="mb-4 rounded border border-green-300 bg-green-50 px-4 py-2 text-sm text-green-700">
                    {flash.success}
                </div>
            )}

            <form onSubmit={handleSubmit} className="max-w-md space-y-4 rounded-lg border bg-white p-6 shadow-sm">
                <div>
                    <label className="block text-sm font-medium text-gray-700">
                        Precio de buffet por persona
                    </label>
                    <input
                        type="number"
                        min="0"
                        step="0.01"
                        value={data.buffet_price}
                        onChange={(e) => setData('buffet_price', e.target.value)}
                        className="mt-1 w-full rounded border px-3 py-2 text-sm"
                    />
                    {errors.buffet_price && <p className="mt-1 text-xs text-red-600">{errors.buffet_price}</p>}
                </div>

                <div>
                    <label className="block text-sm font-medium text-gray-700">
                        Cargo por desperdicio (por ítem)
                    </label>
                    <input
                        type="number"
                        min="0"
                        step="0.01"
                        value={data.waste_fee}
                        onChange={(e) => setData('waste_fee', e.target.value)}
                        className="mt-1 w-full rounded border px-3 py-2 text-sm"
                    />
                    {errors.waste_fee && <p className="mt-1 text-xs text-red-600">{errors.waste_fee}</p>}
                </div>

                <div>
                    <label className="block text-sm font-medium text-gray-700">
                        Tasa de IVA (ej. 0.16 para 16%)
                    </label>
                    <input
                        type="number"
                        min="0"
                        max="1"
                        step="0.0001"
                        value={data.tax_rate}
                        onChange={(e) => setData('tax_rate', e.target.value)}
                        className="mt-1 w-full rounded border px-3 py-2 text-sm"
                    />
                    {errors.tax_rate && <p className="mt-1 text-xs text-red-600">{errors.tax_rate}</p>}
                </div>

                <div>
                    <label className="block text-sm font-medium text-gray-700">
                        Duración de sesión (minutos)
                    </label>
                    <input
                        type="number"
                        min="1"
                        value={data.session_minutes}
                        onChange={(e) => setData('session_minutes', e.target.value)}
                        className="mt-1 w-full rounded border px-3 py-2 text-sm"
                    />
                    {errors.session_minutes && <p className="mt-1 text-xs text-red-600">{errors.session_minutes}</p>}
                </div>

                <div>
                    <label className="block text-sm font-medium text-gray-700">
                        Minutos antes del cierre para último pedido
                    </label>
                    <input
                        type="number"
                        min="1"
                        value={data.last_call_minutes}
                        onChange={(e) => setData('last_call_minutes', e.target.value)}
                        className="mt-1 w-full rounded border px-3 py-2 text-sm"
                    />
                    {errors.last_call_minutes && <p className="mt-1 text-xs text-red-600">{errors.last_call_minutes}</p>}
                </div>

                <button
                    type="submit"
                    disabled={processing}
                    className="w-full rounded bg-blue-600 py-2 text-sm font-medium text-white disabled:opacity-50"
                >
                    Guardar configuración
                </button>
            </form>
        </div>
    );
}
