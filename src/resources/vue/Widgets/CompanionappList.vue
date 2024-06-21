<template>
    <div>
        <btn icon="trash-can" class="btn-outline-danger button-delete float-end" @click="$refs.deleteDialog.show()">
            {{ $t('companion.delete') }}
        </btn>

        <list-table class="m-0" :list="entries" :setup="setup"></list-table>
        <list-more v-if="hasMore" :on-click="loadMore"></list-more>

        <modal-dialog id="delete-warning" ref="deleteDialog" :title="$t('companion.remove-devices')" @click="removeDevices()" :buttons="['delete']" :cancel-focus="true">
            <p>{{ $t('companion.remove-devices-text') }}</p>
        </modal-dialog>
    </div>
</template>

<script>
    import ListTools from './ListTools'
    import ModalDialog from './ModalDialog'

    export default {
        components: {
            ModalDialog
        },
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
            }
        }
    }
</script>
