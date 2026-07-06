import { Head, router } from '@inertiajs/react';
import { useState } from 'react';

function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.content ?? '';
}

async function postJson(url, body) {
    const response = await fetch(url, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            Accept: 'application/json',
            'X-CSRF-TOKEN': csrfToken(),
        },
        body: JSON.stringify(body),
    });

    const data = await response.json().catch(() => ({}));

    return { ok: response.ok, status: response.status, data };
}

function OpenSessionForm({ code }) {
    const [guests, setGuests] = useState(2);
    const [message, setMessage] = useState(null);

    const handleSubmit = async (e) => {
        e.preventDefault();
        setMessage(null);

        const { ok, status, data } = await postJson(`/table/${code}/session`, {
            guests: Number(guests),
        });

        if (ok) {
            router.reload();
        } else {
            setMessage(`Error (${status}): ${data.message ?? 'unknown error'}`);
        }
    };

    return (
        <form onSubmit={handleSubmit} className="space-y-2 rounded border p-4">
            <h2 className="font-bold">No hay sesión activa — abrir mesa</h2>
            <label className="block">
                Comensales:{' '}
                <input
                    type="number"
                    min="1"
                    value={guests}
                    onChange={(e) => setGuests(e.target.value)}
                    className="w-20 border px-1"
                />
            </label>
            <button type="submit" className="rounded bg-blue-600 px-3 py-1 text-white">
                Abrir sesión
            </button>
            {message && <p className="text-red-600">{message}</p>}
        </form>
    );
}

function OrdersList({ orders }) {
    if (!orders || orders.length === 0) {
        return <p className="text-gray-500">Sin pedidos todavía.</p>;
    }

    return (
        <ul className="space-y-2">
            {orders.map((order) => (
                <li key={order.id} className="rounded border p-2">
                    <p className="font-semibold">
                        Ronda {order.round} — estado: {order.status}
                    </p>
                    <ul className="ml-4 list-disc">
                        {order.order_items.map((item) => (
                            <li key={item.id}>
                                {item.qty}x {item.name} (${item.unit_price ?? 0}) — {item.status}
                                {item.note && <span> — nota: {item.note}</span>}
                                {item.order_item_allergens.length > 0 && (
                                    <span>
                                        {' '}
                                        — alérgenos:{' '}
                                        {item.order_item_allergens.map((a) => a.label).join(', ')}
                                    </span>
                                )}
                            </li>
                        ))}
                    </ul>
                </li>
            ))}
        </ul>
    );
}

function CartRow({ dish, allergens, line, onChange }) {
    const toggleAllergen = (allergenId) => {
        const current = line.allergen_ids;
        const next = current.includes(allergenId)
            ? current.filter((id) => id !== allergenId)
            : [...current, allergenId];
        onChange({ ...line, allergen_ids: next });
    };

    return (
        <tr className="border-b">
            <td className="p-1">
                {dish.name} {dish.is_extra ? <span className="text-xs text-orange-600">(extra ${dish.price})</span> : null}
                {dish.per_round_limit && (
                    <span className="text-xs text-gray-500"> — límite/ronda: {dish.per_round_limit}</span>
                )}
            </td>
            <td className="p-1">
                <input
                    type="number"
                    min="0"
                    value={line.qty}
                    onChange={(e) => onChange({ ...line, qty: Number(e.target.value) })}
                    className="w-16 border px-1"
                />
            </td>
            <td className="p-1">
                <input
                    type="text"
                    value={line.note}
                    onChange={(e) => onChange({ ...line, note: e.target.value })}
                    className="w-32 border px-1"
                    placeholder="nota"
                />
            </td>
            <td className="p-1">
                <div className="flex flex-wrap gap-2">
                    {allergens.map((allergen) => (
                        <label key={allergen.id} className="text-xs">
                            <input
                                type="checkbox"
                                checked={line.allergen_ids.includes(allergen.id)}
                                onChange={() => toggleAllergen(allergen.id)}
                            />{' '}
                            {allergen.label}
                        </label>
                    ))}
                </div>
            </td>
        </tr>
    );
}

function MenuAndCart({ code, menu, allergens }) {
    const [cart, setCart] = useState({});
    const [message, setMessage] = useState(null);

    const lineFor = (dishId) => cart[dishId] ?? { qty: 0, note: '', allergen_ids: [] };

    const updateLine = (dishId, line) => {
        setCart((prev) => ({ ...prev, [dishId]: line }));
    };

    const handleSubmit = async () => {
        setMessage(null);

        const items = Object.entries(cart)
            .filter(([, line]) => line.qty > 0)
            .map(([dishId, line]) => ({
                dish_id: Number(dishId),
                qty: line.qty,
                note: line.note || null,
                allergen_ids: line.allergen_ids,
            }));

        if (items.length === 0) {
            setMessage('Agrega al menos un plato con cantidad mayor a 0.');
            return;
        }

        const { ok, status, data } = await postJson(`/table/${code}/orders`, { items });

        if (ok) {
            setCart({});
            router.reload();
        } else {
            setMessage(`Error (${status}): ${JSON.stringify(data.errors ?? data.message)}`);
        }
    };

    return (
        <div className="space-y-4">
            {menu.map((category) => (
                <div key={category.id}>
                    <h3 className="font-bold">{category.label}</h3>
                    {category.dishes.length === 0 ? (
                        <p className="text-sm text-gray-500">Sin platos disponibles.</p>
                    ) : (
                        <table className="w-full text-sm">
                            <tbody>
                                {category.dishes.map((dish) => (
                                    <CartRow
                                        key={dish.id}
                                        dish={dish}
                                        allergens={allergens}
                                        line={lineFor(dish.id)}
                                        onChange={(line) => updateLine(dish.id, line)}
                                    />
                                ))}
                            </tbody>
                        </table>
                    )}
                </div>
            ))}
            <button onClick={handleSubmit} className="rounded bg-green-600 px-3 py-1 text-white">
                Enviar pedido (nueva ronda)
            </button>
            {message && <p className="text-red-600">{message}</p>}
        </div>
    );
}

export default function Table({ code, table, session, menu, allergens }) {
    return (
        <>
            <Head title={`Mesa ${code}`} />
            <div className="mx-auto max-w-3xl space-y-6 p-6">
                <h1 className="text-xl font-bold">
                    Mesa {table.code} ({table.seats} asientos)
                </h1>

                {!session && <OpenSessionForm code={code} />}

                {session && (
                    <>
                        <div className="rounded border p-2">
                            <p>
                                Sesión #{session.id} — {session.guests} comensales — estado: {session.status}
                            </p>
                        </div>

                        <div>
                            <h2 className="mb-2 font-bold">Pedidos de esta sesión</h2>
                            <OrdersList orders={session.orders} />
                        </div>

                        <div>
                            <h2 className="mb-2 font-bold">Menú — armar pedido</h2>
                            <MenuAndCart code={code} menu={menu} allergens={allergens} />
                        </div>
                    </>
                )}
            </div>
        </>
    );
}
