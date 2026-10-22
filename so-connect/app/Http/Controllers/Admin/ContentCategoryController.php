<?php

namespace App\Http\Controllers\Admin;

use App\Helpers\MenuHelper;
use App\Http\Controllers\Controller;
use App\Models\ContentCategory;
use App\Services\ActionLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ContentCategoryController extends Controller
{
    public function index()
    {
        return view('pages.admin.categories.index', [
            'title' => 'Template Categories',
            'categories' => ContentCategory::query()->withCount(['forms', 'reportTemplates'])->ordered()->get(),
            'colors' => ContentCategory::COLORS,
            'icons' => MenuHelper::iconNames(),
        ]);
    }

    public function store(Request $request)
    {
        $category = ContentCategory::create($this->validated($request));
        ActionLogger::log(ActionLogger::CATEGORY_FORM_BUILDER, 'created', 'Created template category "'.$category->name.'"', ['category_id' => $category->id], $category);

        return back()->with('success', 'Category created.');
    }

    public function update(Request $request, ContentCategory $category)
    {
        $category->update($this->validated($request, $category));
        ActionLogger::log(ActionLogger::CATEGORY_FORM_BUILDER, 'updated', 'Updated template category "'.$category->name.'"', ['category_id' => $category->id], $category);

        return back()->with('success', 'Category updated.');
    }

    public function destroy(ContentCategory $category)
    {
        $category->loadCount(['forms', 'reportTemplates']);
        if ($category->forms_count > 0 || $category->report_templates_count > 0) {
            return back()->with('error', 'Reassign the forms and report templates using this category before deleting it.');
        }

        $name = $category->name;
        $id = $category->id;
        $category->delete();
        ActionLogger::log(ActionLogger::CATEGORY_FORM_BUILDER, 'deleted', 'Deleted template category "'.$name.'"', ['category_id' => $id]);

        return back()->with('success', 'Category deleted.');
    }

    private function validated(Request $request, ?ContentCategory $category = null): array
    {
        $request->merge([
            'slug' => Str::slug((string) ($request->input('slug') ?: $request->input('name'))),
            'is_active' => $request->boolean('is_active'),
        ]);

        return $request->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('content_categories', 'name')->ignore($category)],
            'slug' => ['required', 'string', 'max:100', 'regex:/^[a-z0-9-]+$/', Rule::unique('content_categories', 'slug')->ignore($category)],
            'description' => ['nullable', 'string', 'max:1000'],
            'icon' => ['nullable', 'string', Rule::in(MenuHelper::iconNames())],
            'color' => ['required', Rule::in(ContentCategory::COLORS)],
            'display_order' => ['required', 'integer', 'min:0', 'max:10000'],
            'is_active' => ['boolean'],
        ]);
    }
}
