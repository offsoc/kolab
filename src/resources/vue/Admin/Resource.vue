<template>
    <div v-if="resource.id" class="container">
        <div class="card" id="resource-info">
            <div class="card-body">
                <div class="card-title">{{ resource.email }}</div>
                <div class="card-text">
                    <form class="read-only short">
                        <div class="row plaintext">
                            <label for="resourceid" class="col-sm-4 col-form-label">
                                {{ $t('form.id') }} <span class="text-muted">({{ $t('form.created') }})</span>
                            </label>
                            <div class="col-sm-8">
                                <span class="form-control-plaintext" id="resourceid">
                                    {{ resource.id }} <span class="text-muted">({{ resource.created_at }})</span>
                                </span>
                            </div>
                        </div>
                        <div class="row plaintext">
                            <label for="status" class="col-sm-4 col-form-label">{{ $t('form.status') }}</label>
                            <div class="col-sm-8">
                                <span :class="$root.statusClass(resource) + ' form-control-plaintext'" id="status">{{ $root.statusText(resource) }}</span>
                            </div>
                        </div>
                        <div class="row plaintext">
                            <label for="name" class="col-sm-4 col-form-label">{{ $t('form.name') }}</label>
                            <div class="col-sm-8">
                                <span class="form-control-plaintext" id="name">{{ resource.name }}</span>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <ul class="nav nav-tabs mt-3" role="tablist">
            <li class="nav-item">
                <a class="nav-link active" id="tab-settings" href="#resource-settings" role="tab" aria-controls="resource-settings" aria-selected="false" @click="$root.tab">
                    {{ $t('form.settings') }}
                </a>
            </li>
        </ul>
        <div class="tab-content">
            <div class="tab-pane show active" id="resource-settings" role="tabpanel" aria-labelledby="tab-settings">
                <div class="card-body">
                    <div class="card-text">
                        <form class="read-only short">
                            <div class="row plaintext">
                                <label for="invitation_policy" class="col-sm-4 col-form-label">{{ $t('resource.invitation-policy') }}</label>
                                <div class="col-sm-8">
                                    <span class="form-control-plaintext" id="invitation_policy">
                                        {{ resource.config.invitation_policy || $t('form.none') }}
                                    </span>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
    export default {
        data() {
            return {
                resource: { config: {} }
            }
        },
        created() {
            axios.get('/api/v4/resources/' + this.$route.params.resource, { loader: true })
                .then(response => {
                    this.resource = response.data
                })
                .catch(this.$root.errorHandler)
        }
    }
</script>
