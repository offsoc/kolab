<template>
    <div :class="toastClassName()" role="alert" aria-live="assertive" aria-atomic="true">
        <div class="toast-header" :class="className()">
            <svg-icon icon="info-circle" v-if="data.type == 'info'"></svg-icon>
            <svg-icon icon="check-circle" v-else-if="data.type == 'success'"></svg-icon>
            <svg-icon icon="exclamation-circle" v-else-if="data.type == 'error'"></svg-icon>
            <svg-icon icon="exclamation-circle" v-else-if="data.type == 'warning'"></svg-icon>
            <svg-icon :icon="data.icon" v-else-if="data.type == 'custom' && data.icon"></svg-icon>
            <strong>{{ data.title || $t('msg.' + data.type) }}</strong>
            <btn class="btn-close btn-close-white" data-bs-dismiss="toast" :aria-label="$t('btn.close')"></btn>
        </div>
        <div v-if="data.body" v-html="data.body" class="toast-body"></div>
        <div v-else class="toast-body">{{ data.msg }}</div>
        <div v-if="'progress' in data" class="toast-progress">
            <div class="toast-progress-bar" :style="'width: ' + data.progress + '%'"></div>
        </div>
    </div>
</template>

<script>
    import { Toast } from 'bootstrap'

    export default {
        props: {
            data: { type: Object, default: () => {} }
        },
        mounted() {
            this.$el.addEventListener('hidden.bs.toast', () => {
                (this.$el).remove()
                this.$destroy()
            })

            this.$el.addEventListener('shown.bs.toast', () => {
                if (this.data.onShow) {
                    this.data.onShow(this.$el)
                }
            })

            new Toast(this.$el, {
                    animation: true,
                    autohide: this.data.timeout > 0,
                    delay: this.data.timeout
            }).show()
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
            delete() {
                new Toast(this.$el).dispose()
            },
            toastClassName() {
                return 'toast hide toast-' + this.data.type
                    + (this.data.className ? ' ' + this.data.className : '')
            },
            updateProgress(percent) {
                $(this.$el).find('.toast-progress-bar').css('width', percent + '%')
            }
        }
    }
</script>
