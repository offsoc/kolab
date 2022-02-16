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
                    <table id="users-list" class="table table-sm table-hover">
                        <thead>
                            <tr>
                                <th scope="col">{{ $t('form.primary-email') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="user in users" :id="'user' + user.id" :key="user.id" @click="$root.clickRecord">
                                <td>
                                    <svg-icon icon="user" :class="$root.statusClass(user)" :title="$root.statusText(user)"></svg-icon>
                                    <router-link :to="{ path: 'user/' + user.id }">{{ user.email }}</router-link>
                                </td>
                            </tr>
                        </tbody>
                        <list-foot :text="$t('user.users-none')"></list-foot>
                    </table>
                    <list-more v-if="hasMore" :on-click="loadUsers"></list-more>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
    import ListTools from '../Widgets/ListTools'

    export default {
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
