async function initDashboardShipmentCharts() {
    const roots = document.querySelectorAll('[data-shipment-chart]');
    if (roots.length === 0) {
        return;
    }

    const { default: Chart } = await import('chart.js/auto');

    roots.forEach((root) => {
        const raw = root.getAttribute('data-shipment-chart');
        if (!raw) {
            return;
        }

        let payload;
        try {
            payload = JSON.parse(raw);
        } catch {
            return;
        }

        const canvas = root.querySelector('canvas');
        if (!canvas || !payload?.labels?.length) {
            return;
        }

        if (root._shipmentChart) {
            root._shipmentChart.destroy();
        }

        root._shipmentChart = new Chart(canvas.getContext('2d'), {
            type: 'doughnut',
            data: {
                labels: payload.labels,
                datasets: [
                    {
                        data: payload.data,
                        backgroundColor: payload.colors,
                        borderWidth: 0,
                    },
                ],
            },
            options: {
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'bottom' },
                },
            },
        });
    });
}

document.addEventListener('DOMContentLoaded', initDashboardShipmentCharts);
document.addEventListener('livewire:navigated', initDashboardShipmentCharts);

import './pwa.js';
