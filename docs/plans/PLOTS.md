 
**Plot Subdivision
Plot Merger and
Plot Extension**

 
 

 

### **Table Updates – PAA, FileNumber FN, File Indexing FI, Customers Staging CS,Entities Staging ET**

#### **Subdivision**

1. **Creates the number of subdivision records** under the following tables: **PAA, FileNumber FN, File Indexing FI, Customers Staging CS,Entities Staging ET** with the mother File No. under "Related File No" Field & the mother File No. under "Decommissioned Records" Field in **FileNumber FN, File Indexing FI, Customers Staging CS,Entities Staging ET** tables.
2. **The records in PAA** will read "Subdivision" under the transaction type on each subdivision record & comment.
3. **Updates the extant FileNumber FN, File Indexing FI, Customers Staging CS,Entities Staging ET records** by deleting the entry.

#### **Merger**

1. **deletes the extant FileNumber FN, File Indexing FI, Customers Staging CS,Entities Staging ET records** by deleting the mother files (depending on the number of files) under Merger with and creating a new file Number for the merged file.
2. **Creates just one record in PAA**, where instrument type will be "Merger".

#### **Exclusion**

1. **Same as Merger**.







 RECORDING 1

The file is existing. Yes. 192.20 is existing before.

You now divide it into two new blocks. Yes. 1, 2. Yes.

Now, this record automatically is retiring. It's retiring, yes. Then, this one now, maybe they will give it 2026.1, 2026.2. You can write these two file numbers to go and represent file number here.

Push this one to the commission tab. These two files cannot sit. That field can only take one file number.

Yes. We need programming to take file numbers with comma. With comma, yes.

So now, what will happen is you send this record as a standalone record to file number. Standalone record to file indexing. Standalone record to customer.

Standalone record to entity. So it will come, two records will be sent to these tables. Yes.

Now, under the commission, the commission file number. Yes. This will now come there.

Yes. This will come there. Then, this original record will be deleted.

Deleted. Okay. You understand? Yes.

Then, for pra, you send these two records to pra. Yes. And then put transaction type there.

Subdivision. Yes. Then, under comments, you write in under comments, subdivided from this part of the file.

Yes. Uh-huh. So that's for subdivision.

For merger, two files exist. Two files exist, let's say, in 1993-45. Yes.

And they want to merge in 1993-64, uh, 65. They want to merge these two together. So now creates a new one now in 2026, maybe 600.

Yes. Now, these two records. Yes.

They exist in this. Four tables. Four tables.

Four tables, yes. So it's easy. This guy can push these guys to the commission.

Commission. And then this will sit here. Yes.

But the issue is, if it pushes and sits here, it means there will be two records. Yes. And that's duplicate.

Duplicate, yes. So instead of pushing these guys to the commission and coming in, these two records will be deleted. Okay.

This one will now come in. This new file number will come in. Yes.

And then the commission can have comment. Yes. You understand? Yes.

And under PRA, it will come as one record. One record with the transaction type. And this same thing will happen for major.

It will happen for... Extension. Extension. Because they are the same.

Okay. The whole essence is, we should not have duplicate records in these tables with the same file number. Yes.

You understand? Yes. Because if this one comes like this, and comes under file number here, and comes under... It becomes duplicate. Confusing.

Yes. It's not confusing. It becomes duplicate.

Yes, yes. But if we quietly delete these two, and replace with this one, and then under the commission... Commission development. They will come here, the two of them.

Yes. So what we just need to do is to set our system in a way that if there is a search for these files in the future, it will bring this one. From the commission.

You understand? Yes. It will show that... Okay. Because all the transactions under this one will not go.

They will just be joined to this. This is just these ones that are going. Transactions that carry all these file numbers will still maintain.

We are not updating the file number in those transactions. So now, you know, what you now do is, when you imagine this one, you know, this one has his own property ID. Yes.

This one has his own property ID. Yes. So this guy now... Yes.

You know, this guy would have his own new property ID. Yes. All these guys will now inherit this new property ID.

Yes, yes. Yes. You understand? Yes.

But for this one, this guy has a property ID. Yes. This one will have his own new property ID.

Property ID. This one will have his own new property ID. Yes.

So we have to do it in such a way that if they call this guy, these transactions that have this property ID will show. Will show. Because it's history.

Yes. This one, they call it, this one will not show. Yes.

This one plus. This one, just like what we did for that ST thing. So, even this one now, somehow, you know, if there are transactions under this one, they will still carry this guy's property ID.

Yes. So we need to, for this measure, we need to update this new property ID now. Yes.

We need to update all the transactions. Transactions. On this, on the files involved.

Yes. To carry this one. But for subdivisions, this one will get his new property ID.

But it won't update because they are separate. They are like two kids. Yes.

Inheriting this one. Yes. So the transactions under this one will still maintain the old property ID.

Yes. This guy will have his new property ID. But it will be related to this one.

You understand? Yes. So that if they type in this, it will bring out all these transactions before the subdivision was done. Yes.

It won't mean this guy does nothing concerning within the album here. The same thing, if they bring this guy, this one, because it's involved with the two. Yes.

We are collapsing this two to one. This one, we are dividing it into two. That's fine.
 

  RECORDING 2

  (Transcribed by TurboScribe. Go Unlimited to remove this message.)

It has to, so do you know of another method because I'm thinking about the transactions, how we're going to now use this new property ID to update all the transactions on this. It means it's not only adding that one line to bra, it means it's going to query and then update all the transactions on that bra file history. Because you know, I didn't write file history.

Yes, but according to the rules of the property ID, it's per file number. So, for example, this file numbers are different. Yes, now, this guy has, let's say this guy has a property ID, and this one has, let's say, 3, 7, 4, 1. Yes.

Now, this one now, this new one has 1, 0, 7, 0, 0. Yes. The way it is, because it's a major, it should update wherever it sees to whatever, it should change it to this. Whatever it sees this one, it should change it.

So when you call this file in the future, it will bring all the transactions under this one, and all the transactions under this one, because it's a major. Yes. But for this one, this is a subdivision.

So let's say this one is 2, 7, 1, property ID. This new one here is showing you 3, 0, 0, 1. Yes. This one is showing you 3, 0, 0, 2. Yes.

Now, because we're preserving the entire history before these two guys were born, we need to find a way to link this ID to sort out in such a way that when they call this file, all these ones that came before they were born, they show it without this one showing. Yes. And if you call this one, all these ones will show, this one will show without this one.

So how do we do that? And this can only take one property ID. Yes. So how do we do it now? Basically, add the supporting field to the table.

And to relate when they want to call it? Yes. You understand? Because you can update, if you update this one, how do you update this one? Yes. And you can put command here.

So you see the checker balance now. Meanwhile, all the entire history of here, they are very, very, very vast. You understand what I'm saying? Yes.

Let me give you another example. The plaza. Yes.

Let's say this building now, this land building, we're industrially based on a plaza. Yes. So we can sell them to individual units on us.

Yes. Everything that happened before that plaza was built, before that plaza was sold. Yes.

The history is applicable to each unit there. Yes. But whatever you do in your own units, let's say you buy a unit now.

Yes. And then tomorrow you decide to either give it to your son. Yes.

And then your son wants to use that unit to collect mortgage. Yes. When they do a letter search, everything that happened before that place was built, how they changed the land, how the road be changed, till when they built it, till when they now subdivided it.

Yes. It's applicable to that letter search. But with no concern, that letter search no concern with me, I do with my own units.

No concern with your own unit. Yes. So now how do you trace it? Because automatically you have a different property ID.

Yes. The mother place, get your own property ID. So at the point of buying it, even though you are retiring the mother.

Yes. How do you use it in such a way that when you now call your own ID, it will still bring the transactions reports in this ID? Yes. You understand what I'm saying now? So that's the challenge for this subdivision.

But for this merger, Yes. Once we don't merge, it means all those property ID don't... So we need to now still go back to FH, go back to PRA, go back to deed registration, go back to COO, all those four tables. Yes.

You understand? Yes. FH, PRA, COfO, deed registration. Yes.

To update all the property IDs. Okay, sir. With this new property ID.

So this one is an update. Even though you update for... HPT. Property ID.

Yes. But for this one, the existing one is going to be completed. And then this new one will come in.

And then under comments or other, with the commission, we'll put the two file or the three file numbers or the four file numbers. The ledger. Yes.

The ledger that you are trying to process with Yakubu. They say it will take at least one hour. Wow.

So maybe that one will re-sort. Okay.
 
 