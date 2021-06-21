<template>
    <div :class="toastClassName()" role="alert" aria-live="assertive" aria-atomic="true">
        <div class="toast-header" :class="className()">
            <svg-icon icon="info-circle" v-if="data.type == 'info'"></svg-icon>
            <svg-icon icon="check-circle" v-else-if="data.type == 'success'"></svg-icon>
            <svg-icon icon="exclamation-circle" v-else-if="data.type == 'error'"></svg-icon>
            <svg-icon icon="exclamation-circle" v-else-if="data.type == 'warning'"></svg-icon>
            <svg-icon :icon="data.icon" v-else-if="data.type == 'custom' && data.icon"></svg-icon>
            <strong>{{ data.title || $t('msg.' + data.type) }}</strong>
            <button type="button" class="close" data-dismiss="toast" :aria-label="$t('btn.close')">
                <span aria-hidden="true">&times;</span>
            </button>
        </div>
        <div v-if="data.body" v-html="data.body" class="toast-body"></div>
        <div v-else class="toast-body">{{ data.msg }}</div>
    </div>
</template>

<script>
    export default {
        props: {
            data: { type: Object, default: () => {} }
        },
        mounted() {
            $(this.$el)
                .on('hidden.bs.toast', () => {
                    (this.$el).remove()
                    this.$destroy()
                })
                .on('shown.bs.toast', () => {
                    if (this.data.onShow) {
                        this.data.onShow(this.$el)
                    }
                })
                .toast({
                    animation: true,
                    autohide: this.data.timeout > 0,
                    delay: this.data.timeout
                })
                .toast('show')
        },
        methods: {
            className() {
                switch (this.data.type) {
                    case 'error':
                        return 'text-danger'
                    case 'warning':
                    case 'info':
                    case 'success':
                        return 'text-' + this.data.type
                    case 'custom':
                        return this.data.titleClassName || ''
                }
            },
            toastClassName() {
                return 'toast hide toast-' + this.data.type
                    + (this.data.className ? ' ' + this.data.className : '')
            }
        }
    }
</script>
