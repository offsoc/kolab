<template>
    <div v-if="!$root.isLoading" class="container" dusk="dashboard-component">
        <div id="search-box" class="card">
            <div class="card-body">
                <form @submit.prevent="searchUser" class="row justify-content-center">
                    <div class="input-group col-sm-8">
                        <input class="form-control" type="text" placeholder="User ID, email or domain" v-model="search">
                        <div class="input-group-append">
                            <button type="submit" class="btn btn-primary"><svg-icon icon="search"></svg-icon> Search</button>
                        </div>
                    </div>
                </form>
                <table v-if="users.length" class="table table-sm table-hover mt-4">
                    <thead class="thead-light">
                        <tr>
                            <th scope="col">Primary Email</th>
                            <th scope="col">ID</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="user in users" :id="'user' + user.id" :key="user.id">
                            <td>
                                <svg-icon icon="user" :class="$root.userStatusClass(user)" :title="$root.userStatusText(user)"></svg-icon>
                                <router-link :to="{ path: 'user/' + user.id }">{{ user.email }}</router-link>
                            </td>
                            <td>
                                <router-link :to="{ path: 'user/' + user.id }">{{ user.id }}</router-link>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
        <div id="dashboard-nav"></div>
    </div>
</template>

<script>
    export default {
        data() {
            return {
                search: '',
                users: []
            }
        },
        mounted() {
            const authInfo = this.$store.state.isLoggedIn ? this.$store.state.authInfo : null

            if (authInfo) {
                $('#search-box input').focus()
            } else {
                this.$root.startLoading()
                axios.get('/api/auth/info')
                    .then(response => {
                        this.$store.state.authInfo = response.data
                        this.$root.stopLoading()
                        setTimeout(() => { $('#search-box input').focus() }, 10)
                    })
                    .catch(this.$root.errorHandler)
            }
        },
        methods: {
            searchUser() {
                this.users = []

                axios.get('/api/v4/users', { params: { search: this.search } })
                    .then(response => {
                        if (response.data.count == 1) {
                            this.$router.push({ name: 'user', params: { user: response.data.list[0].id } })
                            return
                        }

                        if (response.data.message) {
                            this.$toastr('info', response.data.message)
                        }

                        this.users = response.data.list
                    })
                    .catch(this.$root.errorHandler)
            }
        }
    }
</script>
