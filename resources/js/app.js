import Chart from 'chart.js/auto';

function initDashboardShipmentCharts() {
    document.querySelectorAll('[data-shipment-chart]').forEach((root) => {
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
