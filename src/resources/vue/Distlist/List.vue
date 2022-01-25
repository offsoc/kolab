<template>
    <div class="container">
        <div class="card" id="distlist-list">
            <div class="card-body">
                <div class="card-title">
                    {{ $tc('distlist.list-title', 2) }}
                    <small><sup class="badge bg-primary">{{ $t('dashboard.beta') }}</sup></small>
                    <router-link v-if="!$root.isDegraded()" class="btn btn-success float-end create-list" :to="{ path: 'distlist/new' }" tag="button">
                        <svg-icon icon="users"></svg-icon> {{ $t('distlist.create') }}
                    </router-link>
                </div>
                <div class="card-text">
                    <table class="table table-sm table-hover">
                        <thead>
                            <tr>
                                <th scope="col">{{ $t('distlist.name') }}</th>
                                <th scope="col">{{ $t('distlist.email') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="list in lists" :key="list.id" @click="$root.clickRecord">
                                <td>
                                    <svg-icon icon="users" :class="$root.statusClass(list)" :title="$root.statusText(list)"></svg-icon>
                                    <router-link :to="{ path: 'distlist/' + list.id }">{{ list.name }}</router-link>
                                </td>
                                <td>
                                    <router-link :to="{ path: 'distlist/' + list.id }">{{ list.email }}</router-link>
                                </td>
                            </tr>
                        </tbody>
                        <tfoot class="table-fake-body">
                            <tr>
                                <td colspan="2">{{ $t('distlist.list-empty') }}</td>
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
                lists: []
            }
        },
        created() {
            this.$root.startLoading()

            axios.get('/api/v4/groups')
                .then(response => {
                    this.$root.stopLoading()
                    this.lists = response.data
                })
                .catch(this.$root.errorHandler)
        }
    }
</script>
