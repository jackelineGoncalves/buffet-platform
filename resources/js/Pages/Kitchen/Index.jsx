import { Head } from '@inertiajs/react';

export default function Kitchen({ initialItems, stations }) {
    return (
        <>
            <Head title="Cocina" />
            <div className="p-6">
                <h1 className="text-xl font-bold">Pantalla de cocina</h1>
                <p>{initialItems.length} ítems activos</p>
            </div>
        </>
    );
}
