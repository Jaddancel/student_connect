async function getDataFromType(){
   try{
    const response = await fetch('/api/requests/');
    if (!response.ok) {
        throw new Error('HTTP Error!')
    }
    const data = await response.json();
    console.log(data);
   } catch (error){
        console.error('Fetch error: ', error);
   } 

   // pseudocode
   // AppCount <- 0
   // RejCount <- 0
   // 
   // procedure (data)
   //   read the request type
   //   switch (type)
   //       case (1)
   //           AppCount <- (+1 count of approved )
   //           RejCount <- (+1 count of rejected )
   //       case (2)
   //  
   // checkStatus (row)
   //   if (statusRow == true)
   //       return approved
   //   else if (statusRow == false)
   //       return rejected
   //   else (statusRow == null)
   //       return pending
    

}