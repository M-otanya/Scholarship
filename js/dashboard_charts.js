document.addEventListener("DOMContentLoaded", function () {

    // Retrieve database statistics passed from PHP.
    const data = window.scholarshipChartData;

    if (!data || typeof Chart === "undefined") {
        console.error("Scholarship chart data or Chart.js is missing.");
        return;
    }

    

    const scopeCanvas = document.getElementById("scopeChart");

    if (scopeCanvas) {

        const scopeLabels = data.scopes.map(item =>
            item.scope === "national"
                ? "National"
                : "State / Local"
        );

        const scopeCounts = data.scopes.map(item =>
            Number(item.total)
        );

        new Chart(scopeCanvas, {
            type: "doughnut",

            data: {
                labels: scopeLabels,
                datasets: [{
                    label: "Scholarships",
                    data: scopeCounts,
                    backgroundColor: [
                        "#0d6efd",
                        "#20c997"
                    ],
                    borderWidth: 1
                }]
            },

            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: "bottom"
                    },
                    tooltip: {
                        enabled: true
                    }
                }
            }
        });
    }


    
    const stateCanvas = document.getElementById("stateChart");

    if (stateCanvas) {

        const stateLabels = data.states.map(item =>
            item.state_name
        );

        const stateCounts = data.states.map(item =>
            Number(item.total)
        );

        new Chart(stateCanvas, {
            type: "bar",

            data: {
                labels: stateLabels,
                datasets: [{
                    label: "Scholarships",
                    data: stateCounts,
                    backgroundColor: "#0d6efd",
                    borderWidth: 1
                }]
            },

            options: {
                responsive: true,
                maintainAspectRatio: false,

                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            precision: 0
                        }
                    }
                },

                plugins: {
                    legend: {
                        display: false
                    }
                }
            }
        });
    }


   

    const themeCanvas = document.getElementById("themeChart");

    if (themeCanvas) {

        const themeLabels = data.themes.map(item =>
            item.theme_name
        );

        const themeCounts = data.themes.map(item =>
            Number(item.total)
        );

        new Chart(themeCanvas, {
            type: "bar",

            data: {
                labels: themeLabels,
                datasets: [{
                    label: "Scholarships",
                    data: themeCounts,
                    backgroundColor: "#20c997",
                    borderWidth: 1
                }]
            },

            options: {
                responsive: true,
                maintainAspectRatio: false,

                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            precision: 0
                        }
                    }
                },

                plugins: {
                    legend: {
                        display: false
                    }
                }
            }
        });
    }

});
