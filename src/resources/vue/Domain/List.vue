<template>
    <div class="container">
        <div class="card" id="domain-list">
            <div class="card-body">
                <div class="card-title">
                    {{ $t('user.domains') }}
                    <btn-router v-if="!$root.isDegraded()" class="btn-success float-end" to="domain/new" icon="globe">
                        {{ $t('domain.create') }}
                    </btn-router>
                </div>
                <div class="card-text">
                    <list-widget :list="domains"></list-widget>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
    import ListWidget from './ListWidget'
    import { library } from '@fortawesome/fontawesome-svg-core'

    library.add(
        require('@fortawesome/free-solid-svg-icons/faGlobe').definition,
    )

    export default {
        components: {
            ListWidget
        },
        data() {
            return {
                domains: []
            }
        },
        created() {
            axios.get('/api/v4/domains', { loader: true })
                .then(response => {
                    this.domains = response.data.list
                })
                .catch(this.$root.errorHandler)
        }
    }
</script>
