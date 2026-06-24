(() => {
    const displayInputSelector = '.fi-fo-date-time-picker-display-text-input';
    const nativeDateSelector = 'input[type="date"], input[type="datetime-local"]';

    const normalizePastedDate = (value) => {
        const raw = String(value || '').trim();

        if (!raw) {
            return null;
        }

        const isoMatch = raw.match(/^(\d{4})-(\d{2})-(\d{2})$/);

        if (isoMatch) {
            return `${isoMatch[1]}-${isoMatch[2]}-${isoMatch[3]}`;
        }

        const brMatch = raw.match(/^(\d{2})\/(\d{2})\/(\d{4})$/);

        if (brMatch) {
            return `${brMatch[3]}-${brMatch[2]}-${brMatch[1]}`;
        }

        return null;
    };

    const setNativeDateValue = (input, normalizedDate) => {
        input.value = normalizedDate;
        input.dispatchEvent(new Event('input', { bubbles: true }));
        input.dispatchEvent(new Event('change', { bubbles: true }));
    };

    const setFilamentDatePickerValue = (input, normalizedDate) => {
        const root = input.closest('[x-data]');
        const alpine = window.Alpine;
        const dayjs = window.dayjs;

        if (!root || !alpine || !dayjs) {
            return;
        }

        const component = alpine.$data(root);

        if (!component || typeof component.setState !== 'function') {
            return;
        }

        component.setState(dayjs(`${normalizedDate} 00:00:00`));
    };

    document.addEventListener('paste', (event) => {
        const target = event.target;

        if (!(target instanceof HTMLInputElement)) {
            return;
        }

        const normalizedDate = normalizePastedDate(event.clipboardData?.getData('text'));

        if (!normalizedDate) {
            return;
        }

        if (target.matches(displayInputSelector)) {
            event.preventDefault();
            setFilamentDatePickerValue(target, normalizedDate);

            return;
        }

        if (target.matches(nativeDateSelector)) {
            event.preventDefault();
            setNativeDateValue(target, normalizedDate);
        }
    });
})();
