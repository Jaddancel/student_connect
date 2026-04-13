export function initADChart() {
    const adChartEls = document.querySelectorAll(".ad-chart");
    if (!adChartEls.length) {
        return [];
    }

    const adChartOptions = {
        series: [
            {
                name: "Approved",
                data: [44, 55, 41, 67, 22, 43, 55, 41],
            },
            {
                name: "Rejected",
                data: [13, 23, 20, 8, 13, 27, 13, 23],
            },
        ],
        colors: ["#2a31d8", "#fc4f38"],
        chart: {
            fontFamily: "Outfit, sans-serif",
            type: "bar",
            stacked: true,
            height: 315,
            toolbar: {
                show: false,
            },
            zoom: {
                enabled: false,
            },
        },
        plotOptions: {
            bar: {
                horizontal: false,
                columnWidth: "39%",
                borderRadius: 10,
                borderRadiusApplication: "end",
                borderRadiusWhenStacked: "last",
            },
        },
        dataLabels: {
            enabled: false,
        },
        xaxis: {
            categories: ["Jan", "Feb", "Mar", "Apr", "May", "Jun", "Jul", "Aug"],
            axisBorder: {
                show: false,
            },
            axisTicks: {
                show: false,
            },
        },
        legend: {
            show: true,
            position: "top",
            horizontalAlign: "left",
            fontFamily: "Outfit",
            fontSize: "14px",
            fontWeight: 400,
            markers: {
                size: 5,
                shape: "circle",
                radius: 999,
                strokeWidth: 0,
            },
            itemMargin: {
                horizontal: 10,
                vertical: 0,
            },
        },
        yaxis: {
            title: false,
        },
        grid: {
            yaxis: {
                lines: {
                    show: true,
                },
            },
        },
        fill: {
            opacity: 1,
        },

        tooltip: {
            x: {
                show: false,
            },
            y: {
                formatter: function (val) {
                    return val;
                },
            },
        },
    };

    return Array.from(adChartEls).map((adChartEl) => {
        const adChart = new ApexCharts(adChartEl, adChartOptions);
        adChart.render();
        return adChart;
    });
}


