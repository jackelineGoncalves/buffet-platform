import { Link, useForm } from '@inertiajs/react';
import { useState } from 'react';

function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.content ?? '';
}

function DishForm({ categories, stations, initial, onSubmit, submitLabel, onCancel }) {
    const { data, setData, processing, errors } = useForm(
        initial ?? {
            name: '',
            description: '',
            category_id: categories[0]?.id ?? '',
            station_id: stations[0]?.id ?? '',
            is_extra: false,
            price: '',
            per_round_limit: '',
            sort_order: '',
        },
    );

    const handleSubmit = (e) => {
        e.preventDefault();
        onSubmit(data);
    };

    return (
        <form onSubmit={handleSubmit} className="space-y-3 rounded border bg-white p-4 shadow-sm">
            <div className="grid grid-cols-2 gap-3">
                <div>
                    <label className="block text-xs font-medium text-gray-600">Nombre</label>
                    <input
                        type="text"
                        value={data.name}
                        onChange={(e) => setData('name', e.target.value)}
                        className="mt-1 w-full rounded border px-2 py-1 text-sm"
                        required
                    />
                    {errors.name && <p className="text-xs text-red-600">{errors.name}</p>}
                </div>

                <div>
                    <label className="block text-xs font-medium text-gray-600">Descripción</label>
                    <input
                        type="text"
                        value={data.description ?? ''}
                        onChange={(e) => setData('description', e.target.value)}
                        className="mt-1 w-full rounded border px-2 py-1 text-sm"
                    />
                </div>

                <div>
                    <label className="block text-xs font-medium text-gray-600">Categoría</label>
                    <select
                        value={data.category_id}
                        onChange={(e) => setData('category_id', e.target.value)}
                        className="mt-1 w-full rounded border px-2 py-1 text-sm"
                        required
                    >
                        {categories.map((c) => (
                            <option key={c.id} value={c.id}>{c.label}</option>
                        ))}
                    </select>
                </div>

                <div>
                    <label className="block text-xs font-medium text-gray-600">Estación</label>
                    <select
                        value={data.station_id}
                        onChange={(e) => setData('station_id', e.target.value)}
                        className="mt-1 w-full rounded border px-2 py-1 text-sm"
                        required
                    >
                        {stations.map((s) => (
                            <option key={s.id} value={s.id}>{s.label}</option>
                        ))}
                    </select>
                </div>

                <div className="flex items-center gap-4">
                    <label className="flex items-center gap-2 text-sm">
                        <input
                            type="checkbox"
                            checked={data.is_extra}
                            onChange={(e) => setData('is_extra', e.target.checked)}
                        />
                        Es extra (tiene precio adicional)
                    </label>
                </div>

                {data.is_extra && (
                    <div>
                        <label className="block text-xs font-medium text-gray-600">Precio</label>
                        <input
                            type="number"
                            min="0"
                            step="0.01"
                            value={data.price ?? ''}
                            onChange={(e) => setData('price', e.target.value)}
                            className="mt-1 w-full rounded border px-2 py-1 text-sm"
                        />
                        {errors.price && <p className="text-xs text-red-600">{errors.price}</p>}
                    </div>
                )}

                <div>
                    <label className="block text-xs font-medium text-gray-600">Límite por ronda</label>
                    <input
                        type="number"
                        min="1"
                        value={data.per_round_limit ?? ''}
                        onChange={(e) => setData('per_round_limit', e.target.value)}
                        className="mt-1 w-full rounded border px-2 py-1 text-sm"
                        placeholder="Sin límite"
                    />
                </div>

                <div>
                    <label className="block text-xs font-medium text-gray-600">Orden</label>
                    <input
                        type="number"
                        value={data.sort_order ?? ''}
                        onChange={(e) => setData('sort_order', e.target.value)}
                        className="mt-1 w-full rounded border px-2 py-1 text-sm"
                        placeholder="0"
                    />
                </div>
            </div>

            <div className="flex gap-2">
                <button
                    type="submit"
                    disabled={processing}
                    className="rounded bg-blue-600 px-4 py-1.5 text-sm text-white disabled:opacity-50"
                >
                    {submitLabel}
                </button>
                {onCancel && (
                    <button
                        type="button"
                        onClick={onCancel}
                        className="rounded border px-4 py-1.5 text-sm text-gray-600"
                    >
                        Cancelar
                    </button>
                )}
            </div>
        </form>
    );
}

export default function AdminDishes({ dishes, categories, stations }) {
    const [editingId, setEditingId] = useState(null);
    const [showCreate, setShowCreate] = useState(false);
    const [localDishes, setLocalDishes] = useState(dishes);

    const { post, put, delete: destroy } = useForm();

    const handleCreate = (data) => {
        useForm(data).post('/admin/dishes', {
            onSuccess: () => setShowCreate(false),
        });
    };

    const handleUpdate = (dish, data) => {
        useForm(data).put(`/admin/dishes/${dish.id}`, {
            onSuccess: () => setEditingId(null),
        });
    };

    const handleToggle = async (dish) => {
        const res = await fetch(`/admin/dishes/${dish.id}/toggle`, {
            method: 'PATCH',
            headers: { 'X-CSRF-TOKEN': csrfToken(), Accept: 'application/json' },
        });
        if (res.ok) {
            const { is_available } = await res.json();
            setLocalDishes((prev) =>
                prev.map((d) => (d.id === dish.id ? { ...d, is_available } : d)),
            );
        }
    };

    const handleDelete = (dish) => {
        if (!confirm(`¿Eliminar "${dish.name}"?`)) return;
        useForm().delete(`/admin/dishes/${dish.id}`);
    };

    return (
        <div className="min-h-screen bg-gray-50 p-8">
            <div className="mb-6 flex items-center justify-between">
                <div className="flex items-center gap-4">
                    <Link href="/admin" className="text-sm text-gray-500 hover:text-gray-700">
                        ← Admin
                    </Link>
                    <h1 className="text-2xl font-bold text-gray-800">Platos</h1>
                </div>
                <button
                    onClick={() => setShowCreate((v) => !v)}
                    className="rounded bg-blue-600 px-4 py-2 text-sm text-white"
                >
                    + Nuevo plato
                </button>
            </div>

            {showCreate && (
                <div className="mb-6">
                    <h2 className="mb-2 text-sm font-semibold text-gray-600">Nuevo plato</h2>
                    <DishForm
                        categories={categories}
                        stations={stations}
                        submitLabel="Crear plato"
                        onSubmit={(data) => {
                            useForm(data).post('/admin/dishes', {
                                onSuccess: () => setShowCreate(false),
                            });
                        }}
                        onCancel={() => setShowCreate(false)}
                    />
                </div>
            )}

            <div className="overflow-hidden rounded-lg border bg-white shadow-sm">
                <table className="w-full text-sm">
                    <thead className="bg-gray-50 text-xs uppercase text-gray-500">
                        <tr>
                            <th className="px-4 py-3 text-left">Nombre</th>
                            <th className="px-4 py-3 text-left">Categoría</th>
                            <th className="px-4 py-3 text-left">Estación</th>
                            <th className="px-4 py-3 text-left">Extra</th>
                            <th className="px-4 py-3 text-left">Estado</th>
                            <th className="px-4 py-3 text-left">Acciones</th>
                        </tr>
                    </thead>
                    <tbody className="divide-y">
                        {localDishes.map((dish) => (
                            <>
                                <tr key={dish.id} className={dish.is_available ? '' : 'opacity-50'}>
                                    <td className="px-4 py-3 font-medium">{dish.name}</td>
                                    <td className="px-4 py-3 text-gray-500">{dish.category?.label}</td>
                                    <td className="px-4 py-3 text-gray-500">{dish.station?.label}</td>
                                    <td className="px-4 py-3">
                                        {dish.is_extra ? (
                                            <span className="rounded bg-orange-100 px-2 py-0.5 text-xs text-orange-700">
                                                ${dish.price}
                                            </span>
                                        ) : (
                                            <span className="text-gray-400">—</span>
                                        )}
                                    </td>
                                    <td className="px-4 py-3">
                                        <button
                                            onClick={() => handleToggle(dish)}
                                            className={`rounded px-2 py-0.5 text-xs font-medium ${
                                                dish.is_available
                                                    ? 'bg-green-100 text-green-700'
                                                    : 'bg-red-100 text-red-700'
                                            }`}
                                        >
                                            {dish.is_available ? 'Activo' : 'Inactivo'}
                                        </button>
                                    </td>
                                    <td className="px-4 py-3">
                                        <div className="flex gap-2">
                                            <button
                                                onClick={() => setEditingId(editingId === dish.id ? null : dish.id)}
                                                className="text-blue-600 hover:underline"
                                            >
                                                Editar
                                            </button>
                                            <button
                                                onClick={() => handleDelete(dish)}
                                                className="text-red-600 hover:underline"
                                            >
                                                Eliminar
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                {editingId === dish.id && (
                                    <tr key={`edit-${dish.id}`}>
                                        <td colSpan={6} className="bg-gray-50 px-4 py-3">
                                            <DishForm
                                                categories={categories}
                                                stations={stations}
                                                initial={{
                                                    name: dish.name,
                                                    description: dish.description ?? '',
                                                    category_id: dish.category_id,
                                                    station_id: dish.station_id,
                                                    is_extra: dish.is_extra,
                                                    price: dish.price ?? '',
                                                    per_round_limit: dish.per_round_limit ?? '',
                                                    sort_order: dish.sort_order ?? '',
                                                }}
                                                submitLabel="Guardar cambios"
                                                onSubmit={(data) => {
                                                    useForm(data).put(`/admin/dishes/${dish.id}`, {
                                                        onSuccess: () => setEditingId(null),
                                                    });
                                                }}
                                                onCancel={() => setEditingId(null)}
                                            />
                                        </td>
                                    </tr>
                                )}
                            </>
                        ))}
                        {localDishes.length === 0 && (
                            <tr>
                                <td colSpan={6} className="px-4 py-8 text-center text-gray-400">
                                    Sin platos todavía.
                                </td>
                            </tr>
                        )}
                    </tbody>
                </table>
            </div>
        </div>
    );
}
