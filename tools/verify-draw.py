#!/usr/bin/env python3
"""Offline verifier: python3 verify-draw.py receipt.json [saved-commitment.json]."""
import hashlib,json,sys

def digest(text):
    return hashlib.sha256(text.encode('utf-8')).hexdigest()

def reproduce(seed,pool,count):
    remaining=sorted(pool)
    if len(set(remaining))!=len(remaining) or count<1 or count>len(remaining):
        raise ValueError('Invalid ticket pool or winner count')
    pool_hash=digest(','.join(map(str,remaining)));counter=0;winners=[]
    while len(winners)<count:
        n=len(remaining);limit=2**32-(2**32 % n)
        while True:
            value=int(digest(seed+'|'+pool_hash+'|'+str(counter))[:8],16);counter+=1
            if value<limit:break
        winners.append(remaining.pop(value % n))
    return winners

def verify(receipt,commitment=None):
    record=receipt['record']
    canonical=json.dumps(record,ensure_ascii=False,separators=(',',':'))
    if digest(canonical)!=receipt['sha256']:raise ValueError('Receipt digest mismatch')
    if record['selection']=='sha256-rejection-v1':
        seed=record['seed_reveal']
        if len(seed)!=64 or any(c not in '0123456789abcdef' for c in seed):raise ValueError('Invalid seed')
        if digest(seed)!=record['seed_commitment']:raise ValueError('Seed commitment mismatch')
        if digest(','.join(map(str,sorted(record['eligible_ticket_numbers']))))!=record['pool_sha256']:raise ValueError('Pool digest mismatch')
        if reproduce(seed,record['eligible_ticket_numbers'],len(record['winning_ticket_numbers']))!=record['winning_ticket_numbers']:raise ValueError('Winner reproduction mismatch')
        if commitment and (commitment['raffle_id']!=record['raffle_id'] or commitment['seed_commitment']!=record['seed_commitment']):raise ValueError('Saved earlier commitment differs')
        return 'PASS: receipt integrity and winner reproduction'+('; saved commitment matches' if commitment else '; compare against a commitment saved before entries opened')
    if record['selection']!='php-random-int':raise ValueError('Unknown selection algorithm')
    return 'PASS: legacy receipt integrity only; random selection cannot be reproduced'

if __name__=='__main__':
    try:
        with open(sys.argv[1],encoding='utf-8') as f:receipt=json.load(f)
        commitment=None
        if len(sys.argv)>2:
            with open(sys.argv[2],encoding='utf-8') as f:commitment=json.load(f)
        print(verify(receipt,commitment))
        print('Does not prove pool completeness, independent operation, or legal compliance.')
    except (OSError,ValueError,KeyError,TypeError,IndexError) as error:
        print('FAIL:',error,file=sys.stderr);sys.exit(1)
