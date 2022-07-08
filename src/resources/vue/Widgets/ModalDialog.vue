<template>
    <div class="modal" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content" style="max-height: 90vh">
                <div class="modal-header">
                    <h5 class="modal-title">{{ title }}</h5>
                    <btn class="btn-close" data-bs-dismiss="modal" :aria-label="$t('btn.close')"></btn>
                </div>
                <div class="modal-body overflow-auto">
                    <slot></slot>
                </div>
                <div class="modal-footer">
                    <btn class="btn-secondary modal-cancel" data-bs-dismiss="modal">{{ $t(buttons.length ? 'btn.cancel' : 'btn.close') }}</btn>
                    <btn v-for="(button, index) in buttons" :key="index"
                         :class="btnProperty(button, 'className')"
                         :icon="btnProperty(button, 'icon')"
                         :data-bs-dismiss="btnProperty(button, 'dismiss')"
                         @click="$emit('click', $event)"
                    >
                        {{ $t(btnProperty(button, 'label')) }}
                    </btn>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
    import { Modal } from 'bootstrap'
    import { clearFormValidation } from '../../js/utils'

    // Common buttons
    const buttonTypes = {
        'delete': {
            className: 'btn-danger modal-action',
            dismiss: 'modal',
            label: 'btn.delete',
            icon: 'trash-can'
        },
        submit: {
            className: 'btn-primary modal-action',
            label: 'btn.submit',
            icon: 'check'
        },
        save: {
            className: 'btn-primary modal-action',
            label: 'btn.save',
            icon: 'check'
        }
    }

    export default {
        props: {
            buttons: { type: Array, default: () => [] },
            cancelFocus: { type: Boolean, default: false },
            title: { type: String, default: '' }
        },
        mounted() {
            this.$el.addEventListener('shown.bs.modal', event => {
                clearFormValidation(event.target)

                if (this.cancelFocus) {
                    $(event.target).find('button.modal-cancel').focus()
                } else {
                    $(event.target).find('input,select').first().focus()
                }
            })

            this.dialog = new Modal(this.$el)
        },
        methods: {
            btnProperty(button, property) {
                const isString = typeof button == 'string'
                if (!isString && property in button) {
                    return button[property]
                }

                if (isString && button in buttonTypes && property in buttonTypes[button]) {
                    return buttonTypes[button][property]
                }
            },
            events(events) {
                for (const name in events) {
                    this.$el.addEventListener(name + '.bs.modal', events[name])
                }
            },
            hide() {
                this.dialog.hide()
            },
            show() {
                this.dialog.show()
            }
        }
    }
</script>
