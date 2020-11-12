<template>
    <div id="stats-container" class="container">
    </div>
</template>

<script>
    import { Chart } from 'frappe-charts/dist/frappe-charts.esm.js'

    export default {
        data() {
            return {
                charts: {}
            }
        },
        mounted() {
            ['users', 'users-all', 'income', 'discounts'].forEach(chart => this.loadChart(chart))
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

                this.$root.addLoader(chart)

                axios.get('/api/v4/stats/chart/' + name)
                    .then(response => {
                        this.$root.removeLoader(chart)
                        this.drawChart(name, response.data)
                    })
                    .catch(error => {
                        console.error(error)
                        this.$root.removeLoader(chart)
                        chart.append($('<span>').text('Failed to load data.'))
                    })
            }
        }
    }
</script>
