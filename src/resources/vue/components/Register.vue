<template>
    <div class="container">
        <div class="card" id="step1">
            <div class="card-body">
                <h4 class="card-title">Step 1/3</h4>
                <p class="card-text">
                    Sign up to start your free month.
                </p>
                <form v-on:submit.prevent="submitStep1">
                    <div class="form-group">
                        <label for="register_name" class="sr-only">Your Name</label>
                        <input type="text" class="form-control" id="register_name" placeholder="Your Name" required>
                    </div>
                    <div class="form-group">
                        <label for="register_email" class="sr-only">Existing Email or Phone Number</label>
                        <input type="text" class="form-control" id="register_email" placeholder="Existing Email or Phone Number" required>
                    </div>
                    <button class="btn btn-primary" type="submit">Continue</button>
                </form>
            </div>
        </div>

        <div class="card d-none" id="step2">
            <div class="card-body">
                <h4 class="card-title">Step 2/3</h4>
                <p class="card-text">
                    We sent out a confirmation code to PHONE/EMAIL.
                    Enter the code we sent you, or click the link in the message.
                </p>
                <form v-on:submit.prevent="submitStep2">
                    <div class="form-group">
                        <label for="register_code" class="sr-only">Confirmation Code</label>
                        <input type="text" class="form-control" id="register_code" placeholder="Confirmation Code" required>
                    </div>
                    <button class="btn btn-secondary" type="button" v-on:click="stepBack">Back</button>
                    <button class="btn btn-primary" type="submit">Continue</button>
                </form>
            </div>
        </div>

        <div class="card d-none" id="step3">
            <div class="card-body">
                <h4 class="card-title">Step 3/3</h4>
                <p class="card-text">
                    Create your Kolab identity (you can choose additional addresses later).
                </p>
                <form v-on:submit.prevent="submitStep3">
                    <div class="form-group">
                        <label for="register_login" class="sr-only"></label>
                        <div class="input-group">
                            <input type="text" class="form-control" id="register_login" required>
                            <span class="input-group-text border-left-0 border-right-0 rounded-0">@</span>
                            <select class="custom-select" id="register_domain">
                                <option value="kolabnow.com">kolabnow.com</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="register_password" class="sr-only">Password</label>
                        <input type="password" class="form-control" id="register_password" placeholder="Password" required>
                    </div>
                    <div class="form-group">
                        <label for="register_confirm" class="sr-only">Confirm Password</label>
                        <input type="password" class="form-control" id="register_confirm" placeholder="Confirm Password" required>
                    </div>
                    <button class="btn btn-secondary" type="button" v-on:click="stepBack">Back</button>
                    <button class="btn btn-primary" type="submit">Submit</button>
                </form>
            </div>
        </div>

    </div>
</template>

<script>
    export default {
        methods: {
            submitStep1() {
                // TODO: submit data to the API, validate and get verification code
                $('#step1').addClass('d-none')
                $('#step2').removeClass('d-none').find('input').first().focus()
            },
            submitStep2() {
                // TODO: submit the code to API for verification
                $('#step2').addClass('d-none')
                $('#step3').removeClass('d-none').find('input').first().focus()
            },
            submitStep3() {
                // TODO: submit the data to API to create the user account
            },
            stepBack(e) {
                var card = $(e.target).closest('.card')

                card.prev().removeClass('d-none')
                card.addClass('d-none').find('form')[0].reset()
            }
        }
    }
</script>
