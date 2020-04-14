<template>
    <div :class="'toast hide toast-' + data.type" role="alert" aria-live="assertive" aria-atomic="true">
        <div class="toast-header">
            <svg-icon icon="info-circle" :class="className()" v-if="data.type == 'info'"></svg-icon>
            <svg-icon icon="check-circle" :class="className()" v-else-if="data.type == 'success'"></svg-icon>
            <svg-icon icon="exclamation-circle" :class="className()" v-else-if="data.type == 'error'"></svg-icon>
            <svg-icon icon="exclamation-circle" :class="className()" v-else-if="data.type == 'warning'"></svg-icon>
            <strong :class="className()">{{ data.title || title() }}</strong>
            <button type="button" class="close" data-dismiss="toast" aria-label="Close">
                <span aria-hidden="true">&times;</span>
            </button>
        </div>
        <div class="toast-body">
            {{ data.msg }}
        </div>
    </div>
</template>

<script>
    export default {
        props: {
            data: { type: Object, default: () => { return {} } }
        },
        mounted() {
            $(this.$el).on('hidden.bs.toast', () => {
                    (this.$el).remove()
                    this.$destroy()
                })
                .toast({
                    animation: true,
                    autohide: true,
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
                }
            },
            title() {
                const type = this.data.type
                switch (type) {
                    case 'info':
                        return 'Information';
                    case 'error':
                    case 'warning':
                    case 'success':
                        return type.charAt(0).toUpperCase() + type.slice(1)
                }
            }
        }
    }
</script>
