const express = require('express')
const cors = require('cors')
const helmet = require('helmet')
const rateLimit = require('express-rate-limit')
require('dotenv').config()

const app = express()
const PORT = process.env.PORT || 5000

// Security middleware
app.use(helmet())
app.use(cors({ origin: 'http://localhost:3000' }))
app.use(express.json())

// Rate limiting
const limiter = rateLimit({
    windowMs: 15 * 60 * 1000, // 15 minutes
    max: 100 // limit each IP to 100 requests per window
})
app.use(limiter)

// Test route
app.get('/', (req, res) => {
    res.json({ message: 'HarborSafe API is running!' })
})

app.listen(PORT, () => {
    console.log(`Server running on port ${PORT}`)
})