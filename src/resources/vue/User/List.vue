<template>
    <div class="container">
        <div class="card" id="user-list">
            <div class="card-body">
                <div class="card-title">
                    User Accounts
                    <router-link class="btn btn-success float-right create-user" :to="{ path: 'user/new' }" tag="button">
                        <svg-icon icon="user"></svg-icon> Create user
                    </router-link>
                </div>
                <div class="card-text">
                    <table class="table table-sm table-hover">
                        <thead class="thead-light">
                            <tr>
                                <th scope="col">Primary Email</th>
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
                        <tfoot class="table-fake-body">
                            <tr>
                                <td>There are no users in this account.</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
    export default {
        data() {
            return {
                users: [],
                current_user: null
            }
        },
        created() {
            this.$root.startLoading()

            axios.get('/api/v4/users')
                .then(response => {
                    this.$root.stopLoading()
                    this.users = response.data
                })
                .catch(this.$root.errorHandler)
        }
    }
</script>
