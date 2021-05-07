<template>
    <div class="container">
        <div class="card" id="distlist-list">
            <div class="card-body">
                <div class="card-title">
                    Distribution lists
                    <router-link class="btn btn-success float-right create-list" :to="{ path: 'distlist/new' }" tag="button">
                        <svg-icon icon="users"></svg-icon> Create list
                    </router-link>
                </div>
                <div class="card-text">
                    <table class="table table-sm table-hover">
                        <thead class="thead-light">
                            <tr>
                                <th scope="col">Email</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="list in lists" :key="list.id" @click="$root.clickRecord">
                                <td>
                                    <svg-icon icon="users" :class="$root.distlistStatusClass(list)" :title="$root.distlistStatusText(list)"></svg-icon>
                                    <router-link :to="{ path: 'distlist/' + list.id }">{{ list.email }}</router-link>
                                </td>
                            </tr>
                        </tbody>
                        <tfoot class="table-fake-body">
                            <tr>
                                <td>There are no distribution lists in this account.</td>
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
            // TODO: Find a way to do this in some more global way. Note that it cannot
            //       be done in the vue-router, but maybe the app component?
            if (!this.$root.hasPermission('distlists')) {
                this.$root.errorPage(404)
                return
            }

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
