<?php

namespace App\Http\Controllers;

use App\Http\Requests\PriceItemRequest;
use App\Models\PriceItem;
use App\Services\PriceCatalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class PriceCatalogController extends Controller
{
    public function index(): View
    {
        Gate::authorize('viewAny', PriceItem::class);

        return view('prices.index', ['items' => PriceItem::query()->with('prices')->orderBy('category')->orderBy('name')->paginate(30)]);
    }

    public function store(PriceItemRequest $request, PriceCatalog $catalog): RedirectResponse
    {
        Gate::authorize('manage', PriceItem::class);
        $catalog->create($request->user(), $request->validated());

        return back()->with('status', __('phase9.saved'));
    }

    public function update(PriceItemRequest $request, PriceItem $item, PriceCatalog $catalog): RedirectResponse
    {
        Gate::authorize('manage', PriceItem::class);
        $catalog->update($request->user(), $item, $request->validated());

        return back()->with('status', __('phase9.saved'));
    }
}
