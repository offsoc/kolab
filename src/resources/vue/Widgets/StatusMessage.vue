<template>
    <div v-if="status != 'ready'" :class="statusClass()">
        <div v-if="status == 'init'" class="app-loader small">
            <div class="spinner-border" role="status"></div>
        </div>
        <span v-if="status == 'init'">{{ statusLabel() }}</span>

        <svg-icon v-if="Number(status) >= 400 && status in statusLabels" icon="exclamation-circle"></svg-icon>
        <span v-if="Number(status) >= 400 && status in statusLabels">{{ statusLabel() }}</span>
    </div>
</template>

<script>
    const defaultLabels = {
        init: 'Loading...',
        404: 'Resource not found.'
    }

    export default {
        props: {
            status: { type: String, default: () => 'init' },
            statusLabels: { type: Object, default: () => defaultLabels }
        },
        data() {
            return {
            }
        },
        methods: {
            statusClass() {
                let className = 'status-message'

                if (Number(this.status) >= 400) {
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
