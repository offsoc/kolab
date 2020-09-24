<template>
    <div class="container">
        <div class="card" id="domain-list">
            <div class="card-body">
                <div class="card-title">Domains</div>
                <div class="card-text">
                    <table class="table table-sm table-hover">
                        <thead class="thead-light">
                            <tr>
                                <th scope="col">Name</th>
                                <th scope="col"></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="domain in domains" :key="domain.id" @click="$root.clickRecord">
                                <td>
                                    <svg-icon icon="globe" :class="$root.domainStatusClass(domain)" :title="$root.domainStatusText(domain)"></svg-icon>
                                    <router-link :to="{ path: 'domain/' + domain.id }">{{ domain.namespace }}</router-link>
                                </td>
                                <td class="buttons"></td>
                            </tr>
                        </tbody>
                        <tfoot class="table-fake-body">
                            <tr>
                                <td colspan="2">There are no domains in this account.</td>
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
                domains: []
            }
        },
        created() {
            this.$root.startLoading()

            axios.get('/api/v4/domains')
                .then(response => {
                    this.$root.stopLoading()
                    this.domains = response.data
                })
                .catch(this.$root.errorHandler)
        }
    }
</script>
