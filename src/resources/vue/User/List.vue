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
                        <div v-if="!$root.isDegraded()">
                            <router-link class="btn btn-success ms-1 create-user" :to="{ path: 'user/new' }" tag="button">
                                <svg-icon icon="user"></svg-icon> {{ $t('user.create') }}
                            </router-link>
                        </div>
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
                                    <svg-icon icon="user" :class="$root.userStatusClass(user)" :title="$root.userStatusText(user)"></svg-icon>
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
