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
                            <tr v-for="domain in domains" :key="domain.id">
                                <td><router-link :to="{ path: 'domain/' + domain.id }">{{ domain.namespace }}</router-link></td>
                                <td class="buttons"></td>
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
                domains: []
            }
        },
        created() {
            axios.get('/api/v4/domains')
                .then(response => {
                    this.domains = response.data
                })
                .catch(this.$root.errorHandler)
        }
    }
</script>
