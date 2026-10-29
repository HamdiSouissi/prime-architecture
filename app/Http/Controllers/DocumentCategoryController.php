<?php

namespace App\Http\Controllers;

use App\Http\Requests\DocumentCategoryRequest;
use App\Models\DocumentCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class DocumentCategoryController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', DocumentCategory::class);

        return view('documents.categories', ['categories' => DocumentCategory::orderBy('sort_order')->get()]);
    }

    public function store(DocumentCategoryRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['is_active'] = (bool) ($data['is_active'] ?? false);
        DocumentCategory::create($data);

        return redirect()->route('document-categories.index')->with('status', __('common.saved'));
    }

    public function update(DocumentCategoryRequest $request, DocumentCategory $category): RedirectResponse
    {
        $data = $request->validated();
        $data['is_active'] = (bool) ($data['is_active'] ?? false);
        $category->update($data);

        return redirect()->route('document-categories.index')->with('status', __('common.saved'));
    }

    public function destroy(Request $request, DocumentCategory $category): RedirectResponse
    {
        Gate::authorize('delete', $category);
        DB::transaction(function () use ($category) {
            $category = DocumentCategory::query()->lockForUpdate()->findOrFail($category->id);
            if ($category->documents()->withTrashed()->exists()) {
                throw ValidationException::withMessages(['category' => __('phase4.category_in_use')]);
            }
            $category->delete();
        });

        return redirect()->route('document-categories.index')->with('status', __('common.saved'));
    }
}
