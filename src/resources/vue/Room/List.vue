<template>
    <div class="container">
        <div id="rooms-list" class="card">
            <div class="card-body">
                <div class="card-title">
                    {{ $t('room.list-title') }}
                    <small><sup class="badge bg-primary">{{ $t('dashboard.beta') }}</sup></small>
                    <btn-router v-if="!$root.isDegraded() && $root.hasPermission('settings')" to="room/new" class="btn-success float-end" icon="comments">
                        {{ $t('room.create') }}
                    </btn-router>
                </div>
                <div class="card-text">
                    <list-table :list="rooms" :setup="setup">
                        <template #buttons="{ item }">
                            <btn class="btn-link p-0 lh-1" @click="roomLinkCopy(item)" :icon="['far', 'clipboard']" :title="$t('room.copy-location')"></btn>
                            <btn class="btn-link p-0 lh-1 ms-2" @click="goto(item)" icon="arrow-up-right-from-square" :title="$t('room.goto')"></btn>
                        </template>
                    </list-table>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
    import { ListTable } from '../Widgets/ListTools'
    import { library } from '@fortawesome/fontawesome-svg-core'

    library.add(
        require('@fortawesome/free-regular-svg-icons/faClipboard').definition,
        require('@fortawesome/free-solid-svg-icons/faArrowUpRightFromSquare').definition,
        require('@fortawesome/free-solid-svg-icons/faComments').definition
    )

    export default {
        components: {
            ListTable
        },
        data() {
            return {
                rooms: []
            }
        },
        computed: {
            setup() {
                let setup = {
                    buttons: true,
                    model: 'room',
                    columns: [
                        {
                            prop: 'name',
                            icon: 'comments',
                            link: true,
                            className: 'text-nowrap'
                        },
                        {
                            prop: 'description',
                            link: true
                        }
                    ]
                }

                if (!this.$root.hasPermission('settings')) {
                    setup.footLabel = 'room.list-empty-nocontroller'
                }

                return setup
            }
        },
        mounted() {
            axios.get('/api/v4/rooms', { loader: true })
                .then(response => {
                    this.rooms = response.data.list
                })
                .catch(this.$root.errorHandler)
        },
        methods: {
            goto(room) {
                window.open(this.roomLocation(room), '_blank')
            },
            roomLinkCopy(room) {
                navigator.clipboard.writeText(this.roomLocation(room));
            },
            roomLocation(room) {
                return window.config['app.url'] + '/meet/' + encodeURI(room.name)
            }
        }
    }
</script>
