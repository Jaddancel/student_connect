@extends('layouts.app')

@section('content')
    <x-common.page-breadcrumb pageTitle="Dashboard Builder" />

    <div class="space-y-6">
        <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03] lg:p-6">
            <div class="mb-4 flex flex-col gap-2 md:flex-row md:items-end md:justify-between">
                <div>
                    <h3 class="text-lg font-semibold text-gray-800 dark:text-white/90">Create Widget</h3>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Add and arrange dashboard widgets per role.</p>
                </div>
            </div>

            @if (session('success'))
                <div
                    class="mb-4 rounded-lg border border-success-300 bg-success-50 px-4 py-3 text-sm text-success-700 dark:border-success-500/40 dark:bg-success-500/10 dark:text-success-400">
                    {{ session('success') }}
                </div>
            @endif

            @if ($errors->any())
                <div
                    class="mb-4 rounded-lg border border-error-300 bg-error-50 px-4 py-3 text-sm text-error-700 dark:border-error-500/40 dark:bg-error-500/10 dark:text-error-400">
                    {{ $errors->first() }}
                </div>
            @endif

            <form action="{{ route('superadmin.dashboard-builder.store') }}" method="post"
                class="grid grid-cols-1 gap-4 md:grid-cols-2">
                @csrf

                <div>
                    <label for="role" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Role
                        <span class="text-error-500">*</span></label>
                    <select id="role" name="role"
                        class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:text-white/90">
                        @foreach ($roleOptions as $role)
                            <option value="{{ $role }}" @selected(old('role', 'admin') === $role)>{{ ucfirst($role) }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label for="widget_type"
                        class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Widget Type <span
                            class="text-error-500">*</span></label>
                    <select id="widget_type" name="widget_type"
                        class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:text-white/90">
                        @foreach ($typeOptions as $type)
                            <option value="{{ $type }}" @selected(old('widget_type', 'approval_breakdown') === $type)>
                                {{ str_replace('_', ' ', ucfirst($type)) }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label for="title" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Title
                        <span class="text-error-500">*</span></label>
                    <input type="text" id="title" name="title" value="{{ old('title') }}"
                        class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:text-white/90" />
                </div>

                <div>
                    <label for="sort_order" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Sort
                        Order</label>
                    <input type="number" id="sort_order" name="sort_order" value="{{ old('sort_order', 0) }}"
                        class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:text-white/90" />
                </div>

                <div>
                    <label for="column_span"
                        class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Column Span</label>
                    <input type="number" id="column_span" name="column_span" value="{{ old('column_span', 12) }}"
                        min="1" max="12"
                        class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:text-white/90" />
                </div>

                <div class="md:col-span-2">
                    <label for="config" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Config
                        JSON</label>
                    <textarea id="config" name="config" rows="4"
                        class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:text-white/90"
                        placeholder='{"request_types":["Membership Request"],"columns":["request_id","status"]}'>{{ old('config') }}</textarea>
                </div>

                <div class="flex items-center gap-3">
                    <input type="checkbox" id="is_active" name="is_active" value="1" @checked(old('is_active', true))
                        class="h-4 w-4 rounded border-gray-300 text-brand-500 focus:ring-brand-500/20 dark:border-gray-700" />
                    <label for="is_active" class="text-sm text-gray-700 dark:text-gray-300">Active</label>
                </div>

                <div class="md:col-span-2 flex justify-end">
                    <button type="submit"
                        class="rounded-lg bg-brand-500 px-4 py-2.5 text-sm font-medium text-white transition hover:bg-brand-600">Create
                        Widget</button>
                </div>
            </form>
        </div>

        <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03] lg:p-6">
            <div class="mb-4 flex items-center justify-between">
                <h4 class="text-base font-semibold text-gray-800 dark:text-white/90">Widgets</h4>
                <span
                    class="inline-flex items-center rounded-full bg-gray-100 px-3 py-1 text-xs font-medium text-gray-700 dark:bg-gray-800 dark:text-gray-300">{{ $widgets->count() }}
                    total</span>
            </div>

            @if ($widgets->isEmpty())
                <p class="text-sm text-gray-500 dark:text-gray-400">No widgets configured yet.</p>
            @else
                <div class="space-y-4">
                    @foreach ($widgets as $widget)
                        <form
                            action="{{ route('superadmin.dashboard-builder.update', ['widgetId' => $widget->dashboard_widget_id]) }}"
                            method="post" class="rounded-xl border border-gray-100 p-4 dark:border-gray-800">
                            @csrf
                            @method('patch')
                            <div class="grid grid-cols-1 gap-3 md:grid-cols-12 md:items-end">
                                <div class="md:col-span-4">
                                    <label
                                        class="mb-1 block text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Title</label>
                                    <input type="text" name="title" value="{{ $widget->title }}"
                                        class="dark:bg-dark-900 h-10 w-full rounded-lg border border-gray-300 bg-transparent px-3 text-sm text-gray-800 dark:border-gray-700 dark:text-white/90" />
                                </div>
                                <div class="md:col-span-2">
                                    <label
                                        class="mb-1 block text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Sort</label>
                                    <input type="number" name="sort_order" value="{{ $widget->sort_order }}"
                                        class="dark:bg-dark-900 h-10 w-full rounded-lg border border-gray-300 bg-transparent px-3 text-sm text-gray-800 dark:border-gray-700 dark:text-white/90" />
                                </div>
                                <div class="md:col-span-2">
                                    <label
                                        class="mb-1 block text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Span</label>
                                    <input type="number" name="column_span" value="{{ $widget->column_span }}"
                                        min="1" max="12"
                                        class="dark:bg-dark-900 h-10 w-full rounded-lg border border-gray-300 bg-transparent px-3 text-sm text-gray-800 dark:border-gray-700 dark:text-white/90" />
                                </div>
                                <div class="md:col-span-2">
                                    <label
                                        class="mb-1 block text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Active</label>
                                    <label class="inline-flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                                        <input type="checkbox" name="is_active" value="1"
                                            @checked($widget->is_active)
                                            class="h-4 w-4 rounded border-gray-300 text-brand-500 focus:ring-brand-500/20 dark:border-gray-700" />
                                        Enabled
                                    </label>
                                </div>
                                <div class="md:col-span-2 flex justify-end">
                                    <button type="submit"
                                        class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-gray-800">Save</button>
                                </div>
                                <div class="md:col-span-12 text-xs text-gray-500 dark:text-gray-400">
                                    {{ ucfirst($widget->role) }} · {{ str_replace('_', ' ', $widget->widget_type) }} ·
                                    Created by {{ $widget->creator?->user_email ?? 'system' }}
                                </div>
                            </div>
                        </form>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
@endsection
