SET NAMES utf8mb4;

-- Sample equipment for medicine suppliers (med_reps)
INSERT INTO equipment_inventory
    (supplier_id, equipment_name, equipment_name_ar, category, brand, description, quantity, price, availability)
VALUES
    (1, 'Wheelchair', 'كرسي متحرك', 'mobility', 'MedCare', 'Standard manual wheelchair', 15, 12000.00, 'available'),
    (1, 'Blood Pressure Monitor', 'جهاز قياس ضغط الدم', 'diagnostic', 'Omron', 'Digital automatic BP monitor', 30, 4500.00, 'available'),
    (1, 'Hospital Bed', 'سرير طبي', 'furniture', 'Stiegelmeyer', 'Adjustable electric hospital bed', 5, 85000.00, 'limited'),
    (1, 'Oxygen Concentrator', 'جهاز تركيز الأكسجين', 'respiratory', 'Philips', '5L portable oxygen concentrator', 8, 65000.00, 'available'),
    (1, 'Thermometer', 'ميزان حرارة', 'diagnostic', 'Beurer', 'Digital infrared thermometer', 50, 800.00, 'available'),
    (1, 'Glucose Meter', 'جهاز قياس السكر', 'diagnostic', 'Accu-Chek', 'Blood glucose monitoring system', 25, 2500.00, 'available'),
    (1, 'Medical Masks', 'كمامات طبية', 'protective', 'Generic', 'Surgical face masks box of 50', 200, 350.00, 'available'),
    (1, 'Syringes', 'حقن طبية', 'consumable', 'BD', 'Disposable syringes 10ml pack of 100', 500, 450.00, 'available');
