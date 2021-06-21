<template>
    <div id="search-box" class="card">
        <div class="card-body">
            <form @submit.prevent="searchUser" class="row justify-content-center">
                <div class="input-group col-sm-8">
                    <input class="form-control" type="text" :placeholder="$t('user.search-pl')" v-model="search">
                    <div class="input-group-append">
                        <button type="submit" class="btn btn-primary"><svg-icon icon="search"></svg-icon> {{ $t('btn.search') }}</button>
                    </div>
                </div>
            </form>
            <table v-if="users.length" class="table table-sm table-hover mt-4">
                <thead class="thead-light">
                    <tr>
                        <th scope="col">{{ $t('form.primary-email') }}</th>
                        <th scope="col">{{ $t('form.id') }}</th>
                        <th scope="col" class="d-none d-md-table-cell">{{ $t('form.created') }}</th>
                        <th scope="col" class="d-none d-md-table-cell">{{ $t('form.deleted') }}</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="user in users" :id="'user' + user.id" :key="user.id" :class="user.isDeleted ? 'text-secondary' : ''">
                        <td class="text-nowrap">
                            <svg-icon icon="user" :class="$root.userStatusClass(user)" :title="$root.userStatusText(user)"></svg-icon>
                            <router-link v-if="!user.isDeleted" :to="{ path: 'user/' + user.id }">{{ user.email }}</router-link>
                            <span v-if="user.isDeleted">{{ user.email }}</span>
                        </td>
                        <td>
                            <router-link v-if="!user.isDeleted" :to="{ path: 'user/' + user.id }">{{ user.id }}</router-link>
                            <span v-if="user.isDeleted">{{ user.id }}</span>
                        </td>
                        <td class="d-none d-md-table-cell">{{ toDate(user.created_at) }}</td>
                        <td class="d-none d-md-table-cell">{{ toDate(user.deleted_at) }}</td>
                    </tr>
                </tbody>
            </table>
        </div>
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
            $('#search-box input').focus()
        },
        methods: {
            searchUser() {
                this.users = []

                axios.get('/api/v4/users', { params: { search: this.search } })
                    .then(response => {
                        if (response.data.count == 1 && !response.data.list[0].isDeleted) {
                            this.$router.push({ name: 'user', params: { user: response.data.list[0].id } })
                            return
                        }

                        if (response.data.message) {
                            this.$toast.info(response.data.message)
                        }

                        this.users = response.data.list
                    })
                    .catch(this.$root.errorHandler)
            },
            toDate(datetime) {
                if (datetime) {
                    return datetime.split(' ')[0]
                }
            }
        }
    }
</script>
