<template>
    <div v-if="statusLabel()" :class="statusClass()">
        <div v-if="status == 'init'" class="app-loader small">
            <div class="spinner-border" role="status"></div>
        </div>
        <span v-if="status == 'init'">{{ statusLabel() }}</span>

        <svg-icon v-if="status != 'init' && statusLabel()" :icon="Number(status) >= 400 ? 'exclamation-circle' : 'info-circle'"></svg-icon>
        <span v-if="status != 'init' && statusLabel()">{{ statusLabel() }}</span>
    </div>
</template>

<script>
    const defaultLabels = {
        init: 'Loading...',
        404: 'Resource not found.'
    }

    export default {
        props: {
            status: { type: [String, Number], default: 'init' },
            statusLabels: { type: Object, default: defaultLabels }
        },
        methods: {
            statusClass() {
                let className = 'status-message'

                if (this.status === 'init') {
                    className += ' loading'
                } else if (Number(this.status) >= 400) {
                    className += ' text-danger'
                }

                return className
            },
            statusLabel() {
                if (this.status in this.statusLabels) {
                    return this.statusLabels[this.status]
                }

                if (this.status in defaultLabels) {
                    return defaultLabels[this.status]
                }

                return ''
            }
        }
    }
</script>
