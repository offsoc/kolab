<template>
    <div class="container">
        <div class="card">
            <div class="card-body">
                <h4 class="card-title">Step 1/3</h4>
                <p class="card-text">
                    Sign up to start your free month.
                </p>
                <form id="step1">
                    <div class="form-group">
                        <label for="register_name" class="sr-only">Your Name</label>
                        <input type="text" class="form-control" id="register_name" placeholder="Your Name" required>
                    </div>
                    <div class="form-group">
                        <label for="register_email" class="sr-only">Existing Email or Phone Number</label>
                        <input type="text" class="form-control" id="register_email" placeholder="Existing Email or Phone Number" required>
                    </div>
                    <button class="btn btn-primary" type="button">Continue</button>
                </form>
            </div>
        </div>

        <div class="card">
            <div class="card-body">
                <h4 class="card-title">Step 2/3</h4>
                <p class="card-text">
                    We sent out a confirmation code to PHONE/EMAIL.
                    Enter the code we sent you, or click the link in the message.
                </p>
                <form id="step2">
                    <div class="form-group">
                        <label for="register_code" class="sr-only">Confirmation Code</label>
                        <input type="text" class="form-control" id="register_code" placeholder="Confirmation Code" required>
                    </div>
                    <button class="btn btn-secondary" type="button">Back</button>
                    <button class="btn btn-primary" type="button">Continue</button>
                </form>
            </div>
        </div>

        <div class="card">
            <div class="card-body">
                <h4 class="card-title">Step 3/3</h4>
                <p class="card-text">
                    Create your Kolab identity (you can choose additional addresses later).
                </p>
                <form id="step3">
                    <div class="form-group">
                        <label for="register_login" class="sr-only"></label>
                        <input type="text" class="form-control" id="register_login" required>
                    </div>
                    <div class="form-group">
                        <label for="register_password" class="sr-only">Password</label>
                        <input type="password" class="form-control" id="register_password" placeholder="Password" required>
                    </div>
                    <div class="form-group">
                        <label for="register_confirm" class="sr-only">Confirm Password</label>
                        <input type="password" class="form-control" id="register_confirm" placeholder="Confirm Password" required>
                    </div>
                    <button class="btn btn-secondary" type="button">Back</button>
                    <button class="btn btn-primary" type="button">Submit</button>
                </form>
            </div>
        </div>

    </div>
</template>

<script>
    export default {
        data() {
            return {
                data: 'nothing'
            }
        },
        mounted() {
        }
    }
</script>
