/**
 * Application code for the admin UI
 */

import routes from './routes.js'

window.routes = routes
window.isAdmin = true

require('../app')
