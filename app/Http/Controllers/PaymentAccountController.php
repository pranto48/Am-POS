<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

/**
 * PaymentAccountController
 * Stub controller for payment account routes.
 * Routes are handled by AccountController.
 */
class PaymentAccountController extends Controller
{
    public function index()
    {
        return redirect()->route('account.index');
    }

    public function create()
    {
        return redirect()->route('account.index');
    }

    public function store(Request $request)
    {
        return redirect()->route('account.index');
    }

    public function show($id)
    {
        return redirect()->route('account.index');
    }

    public function edit($id)
    {
        return redirect()->route('account.index');
    }

    public function update(Request $request, $id)
    {
        return redirect()->route('account.index');
    }

    public function destroy($id)
    {
        return redirect()->route('account.index');
    }
}
