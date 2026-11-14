(function () {
    'use strict';

    var I18N = window.GOS_I18N || {};
    var INTL = I18N.intl || 'fr-FR';

    function tr(key, fallback) {
        return I18N[key] || fallback;
    }

    function ready(callback) {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', callback);
        } else {
            callback();
        }
    }

    ready(function () {
        var dataElement = document.getElementById('statsData');
        if (!dataElement) {
            return;
        }

        var data;
        try {
            data = JSON.parse(dataElement.textContent || '{}');
        } catch (error) {
            return;
        }

        var currency = I18N.currency || (document.body && document.body.getAttribute('data-currency')) || 'MAD';
        var palette = ['#0e9f6e', '#2563eb', '#c8a24a', '#7c3aed', '#d97706', '#0891b2', '#dc2626', '#64748b'];

        function formatMoney(value) {
            return new Intl.NumberFormat(INTL, {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            }).format(Number(value) || 0) + ' ' + currency;
        }

        function formatCompact(value) {
            return new Intl.NumberFormat(INTL, {
                notation: 'compact',
                maximumFractionDigits: 1
            }).format(Number(value) || 0);
        }

        function showFallback(canvas, message) {
            if (!canvas) {
                return;
            }
            var wrapper = canvas.closest('.chart-wrap');
            if (wrapper) {
                wrapper.innerHTML = '<div class="chart-empty"><i class="bi bi-bar-chart-line"></i><span>' + message + '</span></div>';
            }
        }

        if (!window.Chart) {
            ['capitalChart', 'flowChart', 'categoryChart'].forEach(function (id) {
                showFallback(document.getElementById(id), tr('chartsOffline', 'Graphiques indisponibles hors connexion. Les données restent consultables ci-dessous.'));
            });
            return;
        }

        window.Chart.defaults.font.family = "'Inter', system-ui, sans-serif";
        window.Chart.defaults.color = '#64748b';

        var capitalCanvas = document.getElementById('capitalChart');
        if (capitalCanvas) {
            new window.Chart(capitalCanvas, {
                type: 'line',
                data: {
                    labels: data.labels || [],
                    datasets: [{
                        label: tr('capital', 'Capital'),
                        data: data.capital || [],
                        borderColor: '#0e9f6e',
                        backgroundColor: 'rgba(14, 159, 110, .12)',
                        fill: true,
                        tension: .34,
                        borderWidth: 2.5,
                        pointRadius: 0,
                        pointHoverRadius: 4,
                        pointHoverBackgroundColor: '#0e9f6e',
                        pointHoverBorderColor: '#ffffff',
                        pointHoverBorderWidth: 2
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: { mode: 'index', intersect: false },
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            backgroundColor: '#0b1220',
                            padding: 12,
                            cornerRadius: 10,
                            displayColors: false,
                            callbacks: {
                                label: function (context) {
                                    return tr('capitalLabel', 'Capital : ') + formatMoney(context.parsed.y);
                                }
                            }
                        }
                    },
                    scales: {
                        x: {
                            grid: { display: false },
                            ticks: { maxTicksLimit: 10, font: { size: 10 }, color: '#94a3b8' }
                        },
                        y: {
                            beginAtZero: true,
                            grid: { color: '#eef2f7' },
                            ticks: {
                                color: '#94a3b8',
                                font: { size: 10 },
                                callback: function (value) {
                                    return formatCompact(value) + ' ' + currency;
                                }
                            }
                        }
                    }
                }
            });
        }

        var flowCanvas = document.getElementById('flowChart');
        if (flowCanvas) {
            new window.Chart(flowCanvas, {
                type: 'bar',
                data: {
                    labels: data.labels || [],
                    datasets: [
                        {
                            label: tr('entries', 'Entrées'),
                            data: data.inValues || [],
                            backgroundColor: 'rgba(14, 159, 110, .78)',
                            hoverBackgroundColor: '#0e9f6e',
                            borderRadius: 5,
                            maxBarThickness: 18
                        },
                        {
                            label: tr('exits', 'Sorties'),
                            data: data.outValues || [],
                            backgroundColor: 'rgba(220, 38, 38, .68)',
                            hoverBackgroundColor: '#dc2626',
                            borderRadius: 5,
                            maxBarThickness: 18
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: { mode: 'index', intersect: false },
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: { usePointStyle: true, boxWidth: 8, font: { size: 11 } }
                        },
                        tooltip: {
                            backgroundColor: '#0b1220',
                            padding: 12,
                            cornerRadius: 10,
                            callbacks: {
                                label: function (context) {
                                    return context.dataset.label + ' : ' + formatMoney(context.parsed.y);
                                }
                            }
                        }
                    },
                    scales: {
                        x: {
                            grid: { display: false },
                            ticks: { maxTicksLimit: 8, font: { size: 10 }, color: '#94a3b8' }
                        },
                        y: {
                            beginAtZero: true,
                            grid: { color: '#eef2f7' },
                            ticks: {
                                color: '#94a3b8',
                                font: { size: 10 },
                                callback: function (value) {
                                    return formatCompact(value) + ' ' + currency;
                                }
                            }
                        }
                    }
                }
            });
        }

        var categoryCanvas = document.getElementById('categoryChart');
        if (categoryCanvas) {
            var categoryLabels = (data.categories && data.categories.labels) || [];
            var categoryValues = (data.categories && data.categories.values) || [];

            if (categoryValues.length === 0) {
                showFallback(categoryCanvas, tr('noCategoryStock', 'Aucun stock valorisé par catégorie pour le moment.'));
            } else {
                new window.Chart(categoryCanvas, {
                    type: 'doughnut',
                    data: {
                        labels: categoryLabels,
                        datasets: [{
                            data: categoryValues,
                            backgroundColor: palette.slice(0, categoryValues.length),
                            borderColor: '#ffffff',
                            borderWidth: 3,
                            hoverOffset: 5
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        cutout: '66%',
                        plugins: {
                            legend: {
                                position: 'bottom',
                                labels: { usePointStyle: true, boxWidth: 8, padding: 14, font: { size: 11 } }
                            },
                            tooltip: {
                                backgroundColor: '#0b1220',
                                padding: 12,
                                cornerRadius: 10,
                                callbacks: {
                                    label: function (context) {
                                        return context.label + ' : ' + formatMoney(context.parsed);
                                    }
                                }
                            }
                        }
                    }
                });
            }
        }
    });
})();
