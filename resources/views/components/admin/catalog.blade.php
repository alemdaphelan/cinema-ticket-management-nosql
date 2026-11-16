@props(['endpoint', 'itemLabel', 'columnCount'])

<div data-admin-catalog data-endpoint="{{ $endpoint }}" data-item-label="{{ $itemLabel }}" data-column-count="{{ $columnCount }}">
    <div class="flex justify-end mb-6">
        <button type="button" data-add-item class="btn-primary !py-2 !px-4 text-sm">+ Thêm {{ $itemLabel }}</button>
    </div>

    <div class="glass rounded-xl overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-cinema-card text-cinema-text-muted">
                    <tr class="border-b border-cinema-border text-left">
                        {{ $headings }}
                        <th scope="col" class="px-4 py-3 text-right">Thao tác</th>
                    </tr>
                </thead>
                <tbody data-catalog-rows>
                    <tr><td colspan="{{ $columnCount }}" class="px-4 py-8 text-center text-cinema-text-muted">Đang tải…</td></tr>
                </tbody>
            </table>
        </div>
    </div>

    <template data-catalog-row>
        <tr class="border-b border-cinema-border hover:bg-cinema-card">
            {{ $cells }}
            <td class="px-4 py-3">
                <div class="flex justify-end gap-3 whitespace-nowrap text-xs font-semibold">
                    <button type="button" data-edit-item class="text-cinema-info-text hover:underline">Sửa</button>
                    <button type="button" data-delete-item class="text-cinema-danger-text hover:underline">Xóa</button>
                </div>
            </td>
        </tr>
    </template>

    <dialog data-item-dialog aria-labelledby="catalog-form-title" class="m-auto max-h-[90dvh] w-[calc(100%-2rem)] max-w-xl overflow-y-auto rounded-2xl border border-cinema-border bg-cinema-surface p-5 text-cinema-text shadow-2xl backdrop:bg-black/70 sm:p-6">
        <form data-item-form class="space-y-4">
            <h2 id="catalog-form-title" class="text-lg font-bold"></h2>
            {{ $fields }}
            <p data-form-error role="alert" class="text-sm text-cinema-danger-text" hidden></p>
            <div class="flex flex-wrap justify-end gap-3 pt-2">
                <button type="button" data-cancel class="rounded-lg border border-cinema-border px-4 py-2 text-sm font-semibold hover:bg-cinema-card">Hủy</button>
                <button type="submit" data-save class="btn-primary !py-2 !px-4 text-sm">Lưu</button>
            </div>
        </form>
    </dialog>
</div>
