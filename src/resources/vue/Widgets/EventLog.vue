<template>
    <div>
        <table class="table table-sm m-0 eventlog">
            <thead>
                <tr>
                    <th scope="col">{{ $t('form.date') }}</th>
                    <th scope="col">{{ $t('log.event') }}</th>
                    <th scope="col">{{ $t('form.comment') }}</th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="event in log" :id="'log' + event.id" :key="event.id">
                    <td class="datetime">{{ event.createdAt }}</td>
                    <td>{{ event.event }}</td>
                    <td class="description">
                        <btn v-if="event.data || event.user" class="btn-link btn-action btn-more" icon="angle-right" :title="$t('form.more')" @click="loadDetails"></btn>
                        <btn v-if="event.data || event.user" class="btn-link btn-action btn-less" icon="angle-down" :title="$t('form.less')" @click="hideDetails"></btn>
                        {{ event.comment }}
                        <pre v-if="event.data" class="details text-monospace p-1 m-1 ms-3">{{ JSON.stringify(event.data, null, 2) }}</pre>
                        <div v-if="event.user" class="details email text-nowrap text-secondary ms-3">
                            <svg-icon icon="user" class="me-1"></svg-icon>{{ event.user }}
                        </div>
                    </td>
                </tr>
            </tbody>
            <list-foot :text="$t('log.list-none')" :colspan="3"></list-foot>
        </table>
        <list-more v-if="hasMore" :on-click="loadLog"></list-more>
    </div>
</template>

<script>
    import ListTools from './ListTools'
    import { library } from '@fortawesome/fontawesome-svg-core'

    library.add(
        require('@fortawesome/free-solid-svg-icons/faAngleDown').definition,
        require('@fortawesome/free-solid-svg-icons/faAngleRight').definition
    )

    export default {
        mixins: [ ListTools ],
        props: {
            objectId: { type: [ String, Number ], default: null },
            objectType: { type: String, default: null },
        },
        data() {
            return {
                log: []
            }
        },
        mounted() {
            this.loadLog({ reset: true })
        },
        methods: {
            loadDetails(event) {
                $(event.target).closest('tr').addClass('open')
            },
            hideDetails(event) {
                $(event.target).closest('tr').removeClass('open')
            },
            loadLog(params) {
                if (this.objectId && this.objectType) {
                    this.listSearch('log', `/api/v4/eventlog/${this.objectType}/${this.objectId}`, params)
                }
            }
        }
    }
</script>
