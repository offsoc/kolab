<template>
    <div>
        <btn icon="trash-can" class="btn-outline-danger button-delete float-end" @click="showDeleteConfirmation()">
            {{ $t('companion.delete') }}
        </btn>
        <list-table class="m-0" :list="entries" :setup="setup"></list-table>
        <list-more v-if="hasMore" :on-click="loadMore"></list-more>
        <div id="delete-warning" class="modal" tabindex="-1" role="dialog">
            <div class="modal-dialog" role="document">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">{{ $t('companion.remove-devices') }}</h5>
                        <btn class="btn-close" data-bs-dismiss="modal" :aria-label="$t('btn.close')"></btn>
                    </div>
                    <div class="modal-body">
                        <p>{{ $t('companion.remove-devices-text') }}</p>
                    </div>
                    <div class="modal-footer">
                        <btn class="btn-secondary modal-cancel" data-bs-dismiss="modal">{{ $t('btn.cancel') }}</btn>
                        <btn class="btn-danger modal-action" data-bs-dismiss="modal" @click="removeDevices()" icon="trash-can">{{ $t('btn.delete') }}</btn>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
    import { Modal } from 'bootstrap'
    import ListTools from './ListTools'

    export default {
        mixins: [ ListTools ],
        data() {
            return {
                entries: [],
                setup: {
                    model: 'companion',
                    columns: [
                        {
                            prop: 'name'
                        },
                        {
                            prop: 'device_id',
                            label: 'companion.deviceid'
                        }
                    ]
                }

            }
        },
        mounted() {
            this.loadMore({ reset: true })
            $('#delete-warning')[0].addEventListener('shown.bs.modal', event => {
                $(event.target).find('button.modal-cancel').focus()
            })
        },
        methods: {
            loadMore(params) {
                this.listSearch('entries', '/api/v4/companion/', params)
            },
            removeDevices() {
                axios.post('/api/v4/companion/revoke')
                    .then(response => {
                        if (response.data.status == 'success') {
                            this.$toast.success(response.data.message)
                        }
                        this.loadMore({ reset: true })
                    })
                    .catch(this.$root.errorHandler)
            },
            showDeleteConfirmation() {
                // Display the warning
                new Modal('#delete-warning').show()
            },
        }
    }
</script>
