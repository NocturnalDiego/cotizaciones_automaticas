<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreContactRequest;
use App\Http\Requests\UpdateContactRequest;
use App\Models\Contact;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class ContactController extends Controller
{
    public function index(): View
    {
        $contacts = Contact::query()
            ->orderBy('name')
            ->orderBy('id')
            ->paginate(12);

        return view('contactos.index', [
            'contacts' => $contacts,
        ]);
    }

    public function view(Contact $contact): View
    {
        return view('contactos.view', [
            'contact' => $contact,
        ]);
    }

    public function create(): View
    {
        return view('contactos.create', [
            'contact' => new Contact(),
        ]);
    }

    public function store(StoreContactRequest $request): RedirectResponse
    {
        $contact = Contact::query()->create($request->validated());

        return redirect()
            ->route('contactos.view', $contact)
            ->with('status', 'Contacto creado correctamente.');
    }

    public function edit(Contact $contact): View
    {
        return view('contactos.edit', [
            'contact' => $contact,
        ]);
    }

    public function update(UpdateContactRequest $request, Contact $contact): RedirectResponse
    {
        $contact->update($request->validated());

        return redirect()
            ->route('contactos.view', $contact)
            ->with('status', 'Contacto actualizado correctamente.');
    }

    public function destroy(Contact $contact): RedirectResponse
    {
        $contact->delete();

        return redirect()
            ->route('contactos.index')
            ->with('status', 'Contacto eliminado correctamente.');
    }
}
