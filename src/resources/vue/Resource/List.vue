<template>
    <div class="container">
        <div class="card" id="resource-list">
            <div class="card-body">
                <div class="card-title">
                    {{ $tc('resource.list-title', 2) }}
                    <small><sup class="badge bg-primary">{{ $t('dashboard.beta') }}</sup></small>
                    <btn-router v-if="!$root.isDegraded()" to="resource/new" class="btn-success float-end" icon="gear">
                        {{ $t('resource.create') }}
                    </btn-router>
                </div>
                <div class="card-text">
                    <list-widget :list="resources"></list-widget>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
    import ListWidget from './ListWidget'
    import { library } from '@fortawesome/fontawesome-svg-core'

    library.add(
        require('@fortawesome/free-solid-svg-icons/faGear').definition,
    )

    export default {
        components: {
            ListWidget
        },
        data() {
            return {
                resources: []
            }
        },
        created() {
            axios.get('/api/v4/resources', { loader: true })
                .then(response => {
                    this.resources = response.data.list
                })
                .catch(this.$root.errorHandler)
        }
    }
</script>
