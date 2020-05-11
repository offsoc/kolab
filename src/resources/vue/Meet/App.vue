<template>
    <router-view v-if="!isLoading && !routerReloading"></router-view>
</template>

<script>
    export default {
        data() {
            return {
                isLoading: true,
                routerReloading: false
            }
        },
        mounted() {
            this.isLoading = false
        },
        methods: {
            routerReload() {
                // Together with beforeRouteUpdate even on a route component
                // allows us to force reload the component. So it is possible
                // to jump from/to page that uses currently loaded component.
                this.routerReloading = true
                this.$nextTick().then(() => {
                    this.routerReloading = false
                })
            }
        }
    }
</script>
