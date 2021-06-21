<template>
    <div class="toast-container" aria-live="polite" aria-atomic="true"></div>
</template>

<script>
    import ToastMessage from './ToastMessage.vue'
    import { i18n } from '../../js/locale'

    export default {
        methods: {
            addToast(data) {
                ToastMessage.i18n = i18n
                const msg = Vue.extend(ToastMessage)
                const instance = new msg({ propsData: { data: data } })
                instance.$mount()
                $(instance.$el).prependTo(this.$el)
            },
            processObjectData(data) {
                if (typeof data === 'object' && data.msg !== undefined) {
                    if (data.type === undefined) {
                        data.type = this.defaultType
                    }
                    if (data.timeout === undefined) {
                        data.timeout = this.defaultTimeout
                    }

                    return data
                }

                return {
                    msg: data.toString(),
                    type: this.defaultType,
                    timeout: this.defaultTimeout
                }
            },
            error(msg, title) {
                let data = this.processObjectData(msg)

                data.type = 'error'

                if (title !== undefined) {
                    data.title = title
                }

                if (!msg.timeout) {
                    data.timeout *= 2
                }

                return this.addToast(data)
            },
            success(msg, title) {
                let data = this.processObjectData(msg)

                data.type = 'success'

                if (title !== undefined) {
                    data.title = title
                }

                return this.addToast(data)
            },
            warning(msg, title) {
                let data = this.processObjectData(msg)

                data.type = 'warning'

                if (title !== undefined) {
                    data.title = title
                }

                if (!msg.timeout) {
                    data.timeout *= 2
                }

                return this.addToast(data)
            },
            info(msg, title) {
                let data = this.processObjectData(msg)

                data.type = 'info'

                if (title !== undefined) {
                    data.title = title
                }

                return this.addToast(data)
            },
            message(data) {
                if (data.type === undefined) {
                    data.type = 'custom'
                }
                if (data.timeout === undefined) {
                    data.timeout = this.defaultTimeout
                }

                return this.addToast(data)
            }
        },
        // Plugin installer method
        install(Vue, options) {
            const defaultOptions = {
                defaultType: 'info',
                defaultTimeout: 5000
            }

            options = $.extend(defaultOptions, [options || {}])

            const Comp = Vue.extend(this)
            const vm = new Comp({ data: options }).$mount()

            document.body.appendChild(vm.$el)

            Vue.prototype.$toast = vm
        }
    }
</script>
