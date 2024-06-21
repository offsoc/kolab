<template>
    <div id="stats-container" class="container"></div>
</template>

<script>
    import { Chart } from 'frappe-charts/dist/frappe-charts.esm.js'

    export default {
        data() {
            return {
                charts: {},
                chartTypes: ['users', 'users-all', 'income', 'payers', 'discounts', 'vouchers']
            }
        },
        mounted() {
            this.chartTypes.forEach(chart => this.loadChart(chart))
        },
        methods: {
            drawChart(name, data) {
                if (!data.title) {
                    return
                }

                const ch = new Chart('#chart-' + name, data)

                this.charts[name] = ch
            },
            loadChart(name) {
                const chart = $('<div>').attr({ id: 'chart-' + name }).appendTo(this.$el)

                axios.get('/api/v4/stats/chart/' + name, { loader: chart })
                    .then(response => {
                        this.drawChart(name, response.data)
                    })
                    .catch(error => {
                        console.error(error)
                        chart.append($('<span>').text(this.$t('msg.loading-failed')))
                    })
            }
        }
    }
</script>
