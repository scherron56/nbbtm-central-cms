SELECT distinct
    f.family_id, hd.anniv_date, hd.contact_id, hd.last_name, hd.first_name, sp.contact_id, sp.last_name, sp.first_name 
FROM nbbtm_central.Families f
JOIN nbbtm_central.contacts hd 
    ON hd.family_id = f.family_id 
    and  hd.contact_id=f.family_id
   AND hd.is_head = 1
JOIN nbbtm_central.contacts sp 
    ON sp.family_id = f.family_id 
    and sp.contact_id<> f.family_id
   AND sp.anniv_date = hd.anniv_date
   AND sp.is_head = 0
WHERE hd.anniv_date IS NOT NULL;