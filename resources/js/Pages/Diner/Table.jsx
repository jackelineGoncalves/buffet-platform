import { Head, router } from '@inertiajs/react';
import { useEffect, useState } from 'react';

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

function BillSummary({ session, setting }) {
    if (!setting) return null;

    const allItems = session.orders?.flatMap((o) => o.order_items ?? []) ?? [];
    const extras = allItems.filter((i) => i.unit_price != null);
    const extrasTotal = extras.reduce(
        (sum, i) => sum + parseFloat(i.unit_price) * i.qty,
        0,
    );
    const buffetTotal = parseFloat(setting.buffet_price) * session.guests;
    const wasteTotal = parseFloat(setting.waste_fee) * (session.waste_count ?? 0);
    const subtotal = buffetTotal + extrasTotal + wasteTotal;
    const tax = subtotal * parseFloat(setting.tax_rate);
    const total = subtotal + tax;

    const fmt = (n) =>
        n.toLocaleString('es-MX', { style: 'currency', currency: 'MXN' });

    return (
        <div className="rounded border border-gray-200 bg-gray-50 p-4 text-sm">
            <h3 className="mb-2 font-semibold text-gray-700">Cuenta estimada</h3>
            <table className="w-full text-gray-600">
                <tbody>
                    <tr>
                        <td>Buffet ({session.guests} persona{session.guests !== 1 ? 's' : ''})</td>
                        <td className="text-right">{fmt(buffetTotal)}</td>
                    </tr>
                    {extrasTotal > 0 && (
                        <tr>
                            <td>Extras</td>
                            <td className="text-right">{fmt(extrasTotal)}</td>
                        </tr>
                    )}
                    {wasteTotal > 0 && (
                        <tr>
                            <td>Cargo por desperdicio ({session.waste_count} ítem{session.waste_count !== 1 ? 's' : ''})</td>
                            <td className="text-right">{fmt(wasteTotal)}</td>
                        </tr>
                    )}
                    <tr className="border-t text-gray-500">
                        <td>IVA ({(parseFloat(setting.tax_rate) * 100).toFixed(0)}%)</td>
                        <td className="text-right">{fmt(tax)}</td>
                    </tr>
                    <tr className="font-bold text-gray-800">
                        <td>Total</td>
                        <td className="text-right">{fmt(total)}</td>
                    </tr>
                </tbody>
            </table>
        </div>
    );
}

function ServiceButtons({ code, session }) {
    const [sent, setSent] = useState({});
    const [message, setMessage] = useState(null);

    const hasPendingItems = (session?.orders ?? [])
        .flatMap((o) => o.order_items ?? [])
        .some((i) => i.status !== 'served');

    const request = async (type) => {
        setMessage(null);
        const { ok, status, data } = await postJson(
            `/table/${code}/service-requests`,
            { type },
        );
        if (ok) {
            setSent((prev) => ({ ...prev, [type]: true }));
        } else if (status === 409) {
            setSent((prev) => ({ ...prev, [type]: true }));
        } else {
            setMessage(data.message ?? 'Error al enviar solicitud.');
        }
    };

    return (
        <div className="space-y-2">
            <div className="flex flex-wrap gap-3">
                <button
                    onClick={() => request('server')}
                    disabled={sent.server}
                    className="rounded bg-blue-600 px-4 py-2 text-sm text-white disabled:opacity-50"
                >
                    {sent.server ? '✓ Mesero avisado' : '🙋 Llamar mesero'}
                </button>

                {hasPendingItems ? (
                    <p className="rounded border border-yellow-300 bg-yellow-50 px-4 py-2 text-sm text-yellow-700">
                        Tienes pedidos aún en preparación o por entregar.
                    </p>
                ) : (
                    <button
                        onClick={() => request('bill')}
                        disabled={sent.bill}
                        className="rounded bg-gray-800 px-4 py-2 text-sm text-white disabled:opacity-50"
                    >
                        {sent.bill ? '✓ Cuenta en camino' : '🧾 Pedir la cuenta'}
                    </button>
                )}
            </div>
            {message && <p className="text-sm text-red-600">{message}</p>}
        </div>
    );
}

export default function Table({ code, table, session, menu, allergens, setting }) {
    useEffect(() => {
        if (!window.Echo || !session) return;

        const channel = window.Echo.channel(`table.${code}`)
            .listen('.DiningSessionClosed', () => {
                router.reload();
            });

        return () => window.Echo.leaveChannel(`table.${code}`);
    }, [code, session?.id]);

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

                        <BillSummary session={session} setting={setting} />

                        <ServiceButtons code={code} session={session} />

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
