<template>
    <div class="container">
        <div class="card" id="distlist-list">
            <div class="card-body">
                <div class="card-title">
                    {{ $tc('distlist.list-title', 2) }}
                    <small><sup class="badge bg-primary">{{ $t('dashboard.beta') }}</sup></small>
                    <btn-router v-if="!$root.isDegraded()" class="btn-success float-end" to="distlist/new" icon="users">
                        {{ $t('distlist.create') }}
                    </btn-router>
                </div>
                <div class="card-text">
                    <list-widget :list="lists"></list-widget>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
    import ListWidget from './ListWidget'
    import { library } from '@fortawesome/fontawesome-svg-core'

    library.add(
        require('@fortawesome/free-solid-svg-icons/faUsers').definition,
    )

    export default {
        components: {
            ListWidget
        },
        data() {
            return {
                lists: []
            }
        },
        created() {
            axios.get('/api/v4/groups', { loader: true })
                .then(response => {
                    this.lists = response.data.list
                })
                .catch(this.$root.errorHandler)
        }
    }
</script>
