<template>
    <router-link :to="to" custom v-slot="{ navigate }">
        <btn :class="className()" :icon="icon" @click="navigate">
            <slot></slot>
        </btn>
    </router-link>
</template>

<script>
    export default {
        props: {
            to: { type: [ Object, String ], default: () => {} },
            icon: { type: [ Array, String ], default: '' },
        },
        methods: {
            className() {
                let label = this.to.length ? this.to : this.to.name

                return ['btn', label.replace('/', '-')]
            }
        }
    }
</script>
