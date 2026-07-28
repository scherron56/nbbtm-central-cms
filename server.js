const express=require('express');
const app = express();
const cors = requie("cors");
app.use(cors());

app.listen(3000, ()=> {
  console.log('Server is running on port 5000')
});