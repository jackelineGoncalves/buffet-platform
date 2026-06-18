import { Head } from '@inertiajs/react';

export default function Table({ code }) {
    return (
        <>
            <Head title={`Mesa ${code}`} />
            <div className="flex min-h-screen items-center justify-center">
                <p className="text-lg">Mesa {code}</p>
            </div>
        </>
    );
}
