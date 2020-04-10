/**
 * Application code for the admin UI
 */

import routes from './routes-admin.js'

window.routes = routes
window.isAdmin = true

require('./app')
