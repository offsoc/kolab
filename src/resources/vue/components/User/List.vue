<template>
    <div class="container">
        <div class="card" id="user-list">
            <div class="card-body">
                <div class="card-title">User Accounts</div>
                <div class="card-text">
                    <table class="table table-hover">
                        <thead class="thead-light">
                            <tr>
                                <th scope="col">Primary Email</th>
                                <th scope="col"></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="user in users">
                                <td><router-link :to="{ path: 'user/' + user.id }">{{ user.email }}</router-link></td>
                                <td></td>
                            </tr>
                        </tbody>
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
                users: []
            }
        },
        created() {
            axios.get('/api/v4/users')
                .then(response => {
                    this.users = response.data
                })
                .catch(error => {
                    this.$root.errorPage(error.response.status, error.response.statusText)
                })
        }
    }
</script>
