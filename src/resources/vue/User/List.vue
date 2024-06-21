<template>
    <div class="container">
        <div class="card" id="user-list">
            <div class="card-body">
                <div class="card-title">
                    {{ $t('user.list-title') }}
                </div>
                <div class="card-text">
                    <div class="mb-2 d-flex">
                        <list-search :placeholder="$t('user.search')" :on-search="searchUsers"></list-search>
                        <btn-router v-if="!$root.isDegraded()" to="user/new" class="btn-success ms-1" icon="user">
                            {{ $t('user.create') }}
                        </btn-router>
                    </div>
                    <list-widget :list="users"></list-widget>
                    <list-more v-if="hasMore" :on-click="loadUsers"></list-more>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
    import ListTools from '../Widgets/ListTools'
    import ListWidget from './ListWidget'

    export default {
        components: {
            ListWidget
        },
        mixins: [ ListTools ],
        data() {
            return {
                users: []
            }
        },
        mounted() {
            this.loadUsers({ init: true })
        },
        methods: {
            loadUsers(params) {
                this.listSearch('users', '/api/v4/users', params)
            },
            searchUsers(search) {
                this.loadUsers({ reset: true, search })
            }
        }
    }
</script>
