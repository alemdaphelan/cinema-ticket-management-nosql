const vietnamTimeZone = 'Asia/Ho_Chi_Minh';
const vietnamOffset = '+07:00';
const dateTime = new Intl.DateTimeFormat('vi-VN', {
    timeZone: vietnamTimeZone, year: 'numeric', month: '2-digit', day: '2-digit',
    hour: '2-digit', minute: '2-digit',
});

function money(value) {
    return Number(value).toLocaleString('vi-VN') + 'đ';
}

function dateLabel(value) {
    return value ? dateTime.format(new Date(value)) : '—';
}

function saleStatus(record) {
    return record.is_active ? ['Đang bán', 'active'] : ['Ngừng bán', 'inactive'];
}

function voucherStatus(record) {
    if (!record.is_active) return ['Đã tắt', 'inactive'];
    if (!record.valid_from || !record.valid_to) return ['Chưa có thời hạn', 'waiting'];
    if (new Date(record.valid_from) > new Date()) return ['Chưa đến hạn', 'waiting'];
    if (new Date(record.valid_to) < new Date()) return ['Hết hạn', 'inactive'];
    return ['Đang hiệu lực', 'active'];
}

const statusStyles = {
    active: ['bg-green-500/20', 'text-cinema-success-text'],
    waiting: ['bg-amber-500/20', 'text-cinema-warning-text'],
    inactive: ['bg-red-500/20', 'text-cinema-danger-text'],
};

function renderValue(cell, record) {
    const value = record[cell.dataset.cell];

    switch (cell.dataset.format) {
        case 'money':
            cell.textContent = money(value);
            break;
        case 'discount':
            cell.textContent = record.discount_type === 'percent'
                ? Number(value).toLocaleString('vi-VN') + '%'
                : money(value);
            break;
        case 'validity':
            cell.textContent = dateLabel(record.valid_from) + ' – ' + dateLabel(record.valid_to);
            break;
        case 'sale-status':
        case 'voucher-status': {
            const [label, tone] = cell.dataset.format === 'sale-status' ? saleStatus(record) : voucherStatus(record);
            cell.textContent = label;
            cell.classList.add(...statusStyles[tone]);
            break;
        }
        default:
            cell.textContent = value ?? '—';
    }
}

function controlValue(input) {
    if (input.type === 'checkbox') return input.checked;
    if (input.type === 'number') return input.valueAsNumber;
    if (input.type === 'datetime-local') return input.value + vietnamOffset;
    return input.value.trim() || null;
}

function setControlValue(input, value) {
    if (input.type === 'checkbox') {
        input.checked = Boolean(value);
    } else if (input.type === 'datetime-local') {
        input.value = value ? new Date(Date.parse(value) + 7 * 60 * 60 * 1000).toISOString().slice(0, 19) : '';
    } else {
        input.value = value ?? '';
    }
}

export function initializeAdminCatalog(catalog) {
    const { endpoint, itemLabel, columnCount } = catalog.dataset;
    const tbody = catalog.querySelector('[data-catalog-rows]');
    const template = catalog.querySelector('[data-catalog-row]');
    const dialog = catalog.querySelector('[data-item-dialog]');
    const form = catalog.querySelector('[data-item-form]');
    const fields = [...form.querySelectorAll('[name]')];
    const save = form.querySelector('[data-save]');
    const cancel = form.querySelector('[data-cancel]');
    const error = form.querySelector('[data-form-error]');
    let editId = null;
    let requestNumber = 0;

    const changeDiscountLimit = () => {
        if (form.elements.discount_value) {
            form.elements.discount_value.max = form.elements.discount_type.value === 'percent' ? '100' : '99999999.99';
        }
    };

    async function request(path = '', method = 'GET', data) {
        const response = await fetch(endpoint + path, {
            method,
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': window.CSRF_TOKEN,
            },
            body: data === undefined ? undefined : JSON.stringify(data),
        });
        const body = response.status === 204 ? {} : await response.json().catch(() => ({}));

        if (!response.ok) {
            const fieldError = Object.values(body.errors ?? {}).flat()[0];
            throw new Error(fieldError || body.message || 'Không thể thực hiện thao tác. Vui lòng thử lại.');
        }

        return body;
    }

    function setListMessage(message, canRetry = false) {
        const row = document.createElement('tr');
        const cell = document.createElement('td');
        cell.colSpan = Number(columnCount);
        cell.className = 'px-4 py-8 text-center text-cinema-text-muted';
        cell.textContent = message;

        if (canRetry) {
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'ml-2 text-cinema-primary-text font-semibold hover:underline';
            button.textContent = 'Thử lại';
            button.addEventListener('click', load);
            cell.append(button);
        }
        row.append(cell);
        tbody.replaceChildren(row);
    }

    function open(record = null) {
        editId = record?.id ?? null;
        form.reset();
        if (record) fields.forEach(input => setControlValue(input, record[input.name]));
        changeDiscountLimit();
        error.hidden = true;
        form.querySelector('h2').textContent = (record ? 'Sửa ' : 'Thêm ') + itemLabel;
        dialog.showModal();
    }

    async function remove(record, button) {
        button.disabled = true;

        try {
            await request('/' + record.id, 'DELETE');
            window.showToast('Đã xóa ' + itemLabel + '.', 'success');
            await load();
        } catch (cause) {
            window.showNotificationModal({ title: 'Không thể xóa', message: cause.message, type: 'error' });
        } finally {
            button.disabled = false;
        }
    }

    async function load() {
        const number = ++requestNumber;

        try {
            const { data } = await request();
            if (number !== requestNumber) return;

            if (!data.length) {
                setListMessage('Chưa có ' + itemLabel + '.');
                return;
            }

            tbody.replaceChildren(...data.map(record => {
                const row = template.content.firstElementChild.cloneNode(true);
                row.querySelectorAll('[data-cell]').forEach(cell => renderValue(cell, record));
                row.querySelector('[data-edit-item]').addEventListener('click', () => open(record));
                const button = row.querySelector('[data-delete-item]');
                button.addEventListener('click', () => window.showConfirmModal({
                    title: 'Xóa ' + itemLabel + '?',
                    message: 'Bạn muốn xóa “' + (record.name ?? record.code) + '” khỏi danh sách?',
                    type: 'warning',
                    confirmText: 'Xóa',
                    onConfirm: () => remove(record, button),
                }));
                return row;
            }));
        } catch (cause) {
            if (number === requestNumber) setListMessage(cause.message, true);
        }
    }

    catalog.querySelector('[data-add-item]').addEventListener('click', () => open());
    cancel.addEventListener('click', () => dialog.close());
    form.elements.discount_type?.addEventListener('change', changeDiscountLimit);
    dialog.addEventListener('cancel', event => {
        if (save.disabled) event.preventDefault();
    });
    form.addEventListener('submit', async event => {
        event.preventDefault();
        if (save.disabled) return;

        const data = Object.fromEntries(fields.map(input => [input.name, controlValue(input)]));
        save.disabled = true;
        cancel.disabled = true;
        save.textContent = 'Đang lưu…';
        error.hidden = true;

        try {
            const result = await request(editId === null ? '' : '/' + editId, editId === null ? 'POST' : 'PUT', data);
            dialog.close();
            window.showToast(result.message, 'success');
            await load();
        } catch (cause) {
            error.textContent = cause.message;
            error.hidden = false;
        } finally {
            save.disabled = false;
            cancel.disabled = false;
            save.textContent = 'Lưu';
        }
    });

    load();
}
