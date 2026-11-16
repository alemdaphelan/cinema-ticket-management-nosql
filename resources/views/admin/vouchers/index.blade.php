<x-layouts.admin title="Quản lý Voucher" header="Quản lý Voucher">
    <x-admin.catalog endpoint="/api/admin/vouchers" item-label="voucher" :column-count="7">
        <x-slot:headings>
            <th scope="col" class="px-4 py-3">Mã voucher</th>
            <th scope="col" class="px-4 py-3">Giá trị giảm</th>
            <th scope="col" class="px-4 py-3">Đơn tối thiểu</th>
            <th scope="col" class="px-4 py-3">Lượt / khách</th>
            <th scope="col" class="px-4 py-3">Thời hạn</th>
            <th scope="col" class="px-4 py-3 text-center">Trạng thái</th>
        </x-slot:headings>

        <x-slot:cells>
            <td data-cell="code" class="px-4 py-3 font-semibold text-cinema-text"></td>
            <td data-cell="discount_value" data-format="discount" class="px-4 py-3 font-semibold text-cinema-accent-text"></td>
            <td data-cell="min_order_value" data-format="money" class="px-4 py-3"></td>
            <td data-cell="max_uses_per_user" class="px-4 py-3"></td>
            <td data-cell="valid_to" data-format="validity" class="px-4 py-3 text-xs"></td>
            <td class="px-4 py-3 text-center">
                <span data-cell="is_active" data-format="voucher-status" class="whitespace-nowrap rounded-full px-2.5 py-1 text-xs"></span>
            </td>
        </x-slot:cells>

        <x-slot:fields>
            <div>
                <label for="voucher-code" class="mb-1.5 block text-sm font-medium">Mã voucher</label>
                <input id="voucher-code" name="code" type="text" maxlength="50" required autofocus class="w-full rounded-lg border border-cinema-border bg-cinema-card px-3 py-2">
            </div>
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <label for="voucher-type" class="mb-1.5 block text-sm font-medium">Kiểu giảm giá</label>
                    <select id="voucher-type" name="discount_type" required class="w-full rounded-lg border border-cinema-border bg-cinema-card px-3 py-2">
                        <option value="percent">Phần trăm (%)</option>
                        <option value="fixed">Số tiền (VNĐ)</option>
                    </select>
                </div>
                <div>
                    <label for="voucher-value" class="mb-1.5 block text-sm font-medium">Giá trị giảm</label>
                    <input id="voucher-value" name="discount_value" type="number" min="0" max="100" step="0.01" required class="w-full rounded-lg border border-cinema-border bg-cinema-card px-3 py-2">
                </div>
                <div>
                    <label for="voucher-min" class="mb-1.5 block text-sm font-medium">Đơn tối thiểu (VNĐ)</label>
                    <input id="voucher-min" name="min_order_value" type="number" min="0" max="99999999.99" step="0.01" value="0" required class="w-full rounded-lg border border-cinema-border bg-cinema-card px-3 py-2">
                </div>
                <div>
                    <label for="voucher-limit" class="mb-1.5 block text-sm font-medium">Số lượt mỗi khách</label>
                    <input id="voucher-limit" name="max_uses_per_user" type="number" min="1" max="2147483647" value="1" required class="w-full rounded-lg border border-cinema-border bg-cinema-card px-3 py-2">
                </div>
                <div>
                    <label for="voucher-from" class="mb-1.5 block text-sm font-medium">Bắt đầu (giờ Việt Nam)</label>
                    <input id="voucher-from" name="valid_from" type="datetime-local" step="1" required class="w-full rounded-lg border border-cinema-border bg-cinema-card px-3 py-2">
                </div>
                <div>
                    <label for="voucher-to" class="mb-1.5 block text-sm font-medium">Hết hạn (giờ Việt Nam)</label>
                    <input id="voucher-to" name="valid_to" type="datetime-local" step="1" required class="w-full rounded-lg border border-cinema-border bg-cinema-card px-3 py-2">
                </div>
            </div>
            <label class="flex items-center gap-2 text-sm font-medium">
                <input name="is_active" type="checkbox" checked class="accent-cinema-primary">
                Kích hoạt
            </label>
        </x-slot:fields>
    </x-admin.catalog>
</x-layouts.admin>
