<x-layouts.admin title="Quản lý Bắp nước" header="Quản lý Bắp nước">
    <x-admin.catalog endpoint="/api/admin/snacks" item-label="bắp nước" :column-count="4">
        <x-slot:headings>
            <th scope="col" class="px-4 py-3">Tên / Combo</th>
            <th scope="col" class="px-4 py-3 text-right">Giá bán</th>
            <th scope="col" class="px-4 py-3 text-center">Trạng thái</th>
        </x-slot:headings>

        <x-slot:cells>
            <td data-cell="name" class="px-4 py-3 font-medium text-cinema-text"></td>
            <td data-cell="price" data-format="money" class="px-4 py-3 text-right font-semibold text-cinema-accent-text"></td>
            <td class="px-4 py-3 text-center">
                <span data-cell="is_active" data-format="sale-status" class="whitespace-nowrap rounded-full px-2.5 py-1 text-xs"></span>
            </td>
        </x-slot:cells>

        <x-slot:fields>
            <div>
                <label for="snack-name" class="mb-1.5 block text-sm font-medium">Tên / Combo</label>
                <input id="snack-name" name="name" type="text" maxlength="255" required autofocus class="w-full rounded-lg border border-cinema-border bg-cinema-card px-3 py-2">
            </div>
            <div>
                <label for="snack-price" class="mb-1.5 block text-sm font-medium">Giá bán (VNĐ)</label>
                <input id="snack-price" name="price" type="number" min="0" max="99999999.99" step="0.01" required class="w-full rounded-lg border border-cinema-border bg-cinema-card px-3 py-2">
            </div>
            <div>
                <label for="snack-image" class="mb-1.5 block text-sm font-medium">Đường dẫn ảnh</label>
                <input id="snack-image" name="image_url" type="text" maxlength="255" placeholder="https://…" class="w-full rounded-lg border border-cinema-border bg-cinema-card px-3 py-2">
            </div>
            <label class="flex items-center gap-2 text-sm font-medium">
                <input name="is_active" type="checkbox" checked class="accent-cinema-primary">
                Đang bán
            </label>
        </x-slot:fields>
    </x-admin.catalog>
</x-layouts.admin>
